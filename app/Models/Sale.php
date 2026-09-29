<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor, RequiresApproval;

    protected $fillable = [
        'customer_id', 'sale_date', 'subtotal_rupees', 'discount_rupees',
        'vehicle_charge_rupees', 'total_rupees', 'due_date', 'reference', 'reference_user_id', 'is_draft',
    ];

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'due_date' => 'date', 'is_draft' => 'boolean',
            'subtotal_rupees' => 'decimal:2', 'discount_rupees' => 'decimal:2',
            'vehicle_charge_rupees' => 'decimal:2', 'total_rupees' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function referenceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reference_user_id')->withTrashed();
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
