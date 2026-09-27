<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UpcomingOrder extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor;

    protected $fillable = ['customer_id', 'scheduled_date', 'note'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(UpcomingOrderItem::class);
    }
}
