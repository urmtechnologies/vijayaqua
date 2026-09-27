<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use TracksCreator;

    protected $fillable = ['name', 'mobile'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function upcomingOrders(): HasMany
    {
        return $this->hasMany(UpcomingOrder::class);
    }
}
