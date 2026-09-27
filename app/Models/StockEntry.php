<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockEntry extends Model
{
    use TracksCreator, TracksEditor;

    protected $fillable = ['entry_date', 'type'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }
}
