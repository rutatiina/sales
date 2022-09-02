<?php

namespace Rutatiina\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Rutatiina\Tenant\Scopes\TenantIdScope;

class SaleItem extends Model
{
    use LogsActivity;

    protected static $logName = 'Sale Item';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_sales_items';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

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

    public function invoice()
    {
        return $this->belongsTo('Rutatiina\Sales\Models\Sale', 'sale_id');
    }

    public function taxes()
    {
        return $this->hasMany('Rutatiina\Sales\Models\SaleItemTax', 'sale_item_id', 'id');
    }

}
