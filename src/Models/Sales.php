<?php

namespace Rutatiina\Sales\Models;

use Bkwld\Cloner\Cloneable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rutatiina\Sales\Scopes\StatusEditedScope;

class Sales extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use Cloneable;

    protected static $logName = 'Sale';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_sales';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'contact_id' => 'integer',
    ];

    protected $cloneable_relations = [
        'comments',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at',
    ];

    protected $appends = [
        'number_string',
        'total_in_words',
        'payment_status',
        'balance',
        'ledgers'
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);
        static::addGlobalScope(new StatusEditedScope);

        self::deleted(function($txn) { // before delete() method call this
             $txn->items()->each(function($row) {
                $row->delete();
             });
             $txn->comments()->each(function($row) {
                $row->delete();
             });
        });

        self::restored(function($txn) {
             $txn->items()->each(function($row) {
                $row->restore();
             });
             $txn->comments()->each(function($row) {
                $row->restore();
             });
        });
    }
    
    /**
     * Scope a query to only include approved records users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
    
    /**
     * Scope a query to only include not canceled records
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotCancelled($query)
    {
        return $query->where(function($q) {
            $q->where('canceled', 0);
            $q->orWhereNull('canceled');
        });
    }

    public function rgGetAttributes()
    {
        $attributes = [];
        $describeTable =  DB::connection('tenant')->select('describe ' . $this->getTable());

        foreach ($describeTable  as $row) {

            if (in_array($row->Field, ['id', 'created_at', 'updated_at', 'deleted_at', 'tenant_id', 'user_id'])) continue;

            if (in_array($row->Field, ['currencies', 'taxes'])) {
                $attributes[$row->Field] = [];
                continue;
            }

            if ($row->Default == '[]') {
                $attributes[$row->Field] = [];
            } else {
                $attributes[$row->Field] = ''; //$row->Default; //null affects laravel validation
            }
        }

        //add the relationships
        $attributes['type'] = [];
        $attributes['debit_account'] = [];
        $attributes['credit_account'] = [];
        $attributes['items'] = [];
        $attributes['ledgers'] = [];
        $attributes['comments'] = [];
        $attributes['debit_contact'] = [];
        $attributes['credit_contact'] = [];
        $attributes['recurring'] = [];

        return $attributes;
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = strtolower($value);
    }

    public function getContactAddressArrayAttribute()
    {
        return preg_split("/\r\n|\n|\r/", $this->contact_address);
    }

    public function getNumberStringAttribute()
    {
        return $this->number_prefix.(str_pad(($this->number), $this->number_length, "0", STR_PAD_LEFT)).$this->number_postfix;
    }

    public function getTotalInWordsAttribute()
    {
        $f = new \NumberFormatter( locale_get_default(), \NumberFormatter::SPELLOUT );
        return ucfirst($f->format($this->total));
    }

    public function getBalanceAttribute()
    {
        return $this->total - $this->total_paid;
    }

    public function getPaymentStatusAttribute()
    {
        if (!$this->total_paid || $this->total_paid == 0)
        {
            return 'Unpaid';
        }
        elseif($this->total_paid > 0 && $this->total_paid < $this->total)
        {
            return 'Partially Paid';
        }
        elseif($this->total_paid == $this->total)
        {
            return 'Paid';
        }
        else
        {
            return '--';
        }
    }

    public function debit_account()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'debit_financial_account_code');
    }

    public function credit_account()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'credit_financial_account_code');
    }

    public function items()
    {
        return $this->hasMany('Rutatiina\Sales\Models\SalesItem', 'sales_id')->orderBy('id', 'asc');
    }

    public function getLedgersAttribute($txn = null)
    {
        // if (!$txn) $this->items;

        $txn = $txn ?? $this;

        $txn = (is_object($txn)) ? $txn : collect($txn);
        
        $ledgers = [];

        foreach ($txn->items as $item)
        {
            //CR ledger
            $ledgers[$item->credit_financial_account_code]['financial_account_code'] = $item->credit_financial_account_code;
            $ledgers[$item->credit_financial_account_code]['effect'] = 'credit';
            $ledgers[$item->credit_financial_account_code]['total'] = ($ledgers[$item->credit_financial_account_code]['total'] ?? 0) + $item->taxable_amount;
            $ledgers[$item->credit_financial_account_code]['contact_id'] = $txn->contact_id;
        }

        //DR ledger
        $ledgers[] = [
            'financial_account_code' => $txn->debit_financial_account_code,
            'effect' => 'debit',
            'total' => $txn->total,
            'contact_id' => $txn->contact_id
        ];

        foreach ($ledgers as &$ledger)
        {
            $ledger['tenant_id'] = $txn->tenant_id;
            $ledger['date'] = $txn->date;
            $ledger['base_currency'] = $txn->base_currency;
            $ledger['quote_currency'] = $txn->quote_currency;
            $ledger['exchange_rate'] = $txn->exchange_rate;
        }
        unset($ledger);

        return collect($ledgers);
    }

    public function comments()
    {
        return $this->hasMany('Rutatiina\Sales\Models\SalesComment', 'sales_id')->latest();
    }

    public function contact()
    {
        return $this->hasOne('Rutatiina\Contact\Models\Contact', 'id', 'contact_id');
    }

    public function annexes()
    {
        return $this->hasMany('Rutatiina\Sales\Models\Annex', 'sales_id', 'id');
    }

    public function item_taxes()
    {
        return $this->hasMany('Rutatiina\Sales\Models\SalesItemTax', 'sales_id', 'id');
    }

    public function getTaxesAttribute()
    {
        $grouped = [];
        $this->item_taxes->load('tax'); //the values of the tax are used by the display of the document on the from end

        foreach($this->item_taxes as $item_tax)
        {
            if (isset($grouped[$item_tax->tax_code]))
            {
                $grouped[$item_tax->tax_code]['amount'] += $item_tax['amount'];
                $grouped[$item_tax->tax_code]['inclusive'] += $item_tax['inclusive'];
                $grouped[$item_tax->tax_code]['exclusive'] += $item_tax['exclusive'];
            }
            else
            {
                $grouped[$item_tax->tax_code] = $item_tax;
            }
        }
        return $grouped;
    }

    public function columns()
    {
        return Schema::connection($this->connection)->getColumnListing($this->table);
    }

}
