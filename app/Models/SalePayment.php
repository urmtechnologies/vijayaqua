<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use TracksCreator;

    protected $fillable = ['sale_id', 'payment_date', 'amount_rupees', 'method', 'reference'];

    protected function casts(): array
    {
        return ['payment_date' => 'date'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
