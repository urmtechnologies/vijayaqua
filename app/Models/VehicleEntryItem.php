<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleEntryItem extends Model
{
    protected $fillable = ['product_id', 'product_name', 'cartons'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(VehicleEntry::class, 'vehicle_entry_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
