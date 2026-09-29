<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use TracksCreator;

    protected $fillable = ['name', 'mobile', 'business_name'];

    protected function casts(): array
    {
        return ['total_sales_rupees' => 'decimal:2', 'total_paid_rupees' => 'decimal:2', 'balance_rupees' => 'decimal:2'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function upcomingOrders(): HasMany
    {
        return $this->hasMany(UpcomingOrder::class);
    }
}
