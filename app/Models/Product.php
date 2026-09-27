<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor;

    protected $fillable = ['name', 'status'];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function soldItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
