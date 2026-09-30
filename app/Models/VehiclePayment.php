<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePayment extends Model
{
    use TracksCreator;

    protected $fillable = ['reference_user_id', 'payment_date', 'entry_type', 'method', 'amount_rupees', 'note'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount_rupees' => 'decimal:2'];
    }

    public function referenceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reference_user_id')->withTrashed();
    }
}
