<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpcomingOrderItem extends Model
{
    protected $fillable = ['product_id', 'product_name', 'cartons'];

    protected function casts(): array
    {
        return ['cartons' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(UpcomingOrder::class, 'upcoming_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
