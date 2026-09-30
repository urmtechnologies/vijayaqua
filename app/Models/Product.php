<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor, RequiresApproval;

    protected $fillable = ['name', 'status'];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockEntryItem::class)->whereHas('entry');
    }

    public function soldItems(): HasMany
    {
        return $this->hasMany(SaleItem::class)->whereHas('sale');
    }

    public function vehicleItems(): HasMany
    {
        return $this->hasMany(VehicleEntryItem::class)->whereHas('entry');
    }
}
