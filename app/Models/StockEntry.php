<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockEntry extends Model
{
    use TracksCreator;

    protected $fillable = ['entry_date'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }
}
