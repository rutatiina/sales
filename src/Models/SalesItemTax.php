<?php

namespace Rutatiina\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Rutatiina\Tenant\Scopes\TenantIdScope;

class SalesItemTax extends Model
{
    use LogsActivity;

    protected static $logName = 'Sale Item';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_sales_item_taxes';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $casts = [
        'sales_id' => 'integer',
        'sales_item_id' => 'integer',
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

    public function getTaxesAttribute($value)
    {
        $_array_ = json_decode($value);
        if (is_array($_array_)) {
            return $_array_;
        } else {
            return [];
        }
    }

    public function tax()
    {
        return $this->hasOne('Rutatiina\Tax\Models\Tax', 'code', 'tax_code');
    }

    public function invoice()
    {
        return $this->belongsTo('Rutatiina\Sales\Models\Sale', 'sales_id', 'id');
    }

    public function invoice_item()
    {
        return $this->belongsTo('Rutatiina\Sales\Models\SalesItem', 'sales_item_id', 'id');
    }

}
