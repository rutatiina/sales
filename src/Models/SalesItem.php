<?php

namespace Rutatiina\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesItem extends Model
{
    use SoftDeletes;
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

    protected $appends = [
        'inventory_tracking',
    ];

    protected $casts = [
        'item_id' => 'integer',
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

    public function sale()
    {
        return $this->belongsTo('Rutatiina\Sales\Models\Sales', 'sales_id');
    }

    public function taxes()
    {
        return $this->hasMany('Rutatiina\Sales\Models\SalesItemTax', 'sales_item_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo('Rutatiina\Item\Models\Item', 'item_id');
    }

    public function getInventoryTrackingAttribute()
    {
        return optional($this->item)->inventory_tracking;
    }

}
