<?php

namespace Rutatiina\Sales\Models;

use Rutatiina\Sales\Models\Sales;
use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesSetting extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logName = 'Sale Settings';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_sales_settings';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'debit_financial_account_code' => 'integer',
        'credit_financial_account_code' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'default_date',
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
    }

    public function rgGetAttributes()
    {
        $attributes = [];
        $describeTable =  \DB::connection('tenant')->select('describe ' . $this->getTable());

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

        return $attributes;
    }

    public function financial_account_to_debit()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'debit_financial_account_code');
    }

    public function financial_account_to_credit()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'credit_financial_account_code');
    }

    public function getDefaultDateAttribute()
    {
        switch ($this->default_date_method) 
        {
            case 'today':
                return date('Y-m-d');
                break;
            case 'last_entry_date':
                return optional(Sales::orderBy('id', 'desc')->first())->date ?? date('Y-m-d');
                break;
            default:
                return date('Y-m-d');
                break;
        }
    }

}
