<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['product_id', 'product_name', 'cartons', 'rate_rupees', 'line_total_rupees'];

    protected function casts(): array
    {
        return ['rate_rupees' => 'decimal:2', 'line_total_rupees' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->where('approval_status', 'approved');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
