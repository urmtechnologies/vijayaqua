<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use TracksCreator;

    protected $fillable = [
        'customer_id', 'sale_date', 'subtotal_rupees', 'discount_rupees',
        'vehicle_charge_rupees', 'total_rupees', 'due_date', 'reference',
    ];

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'due_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}
