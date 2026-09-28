<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalePayment extends Model
{
    use TracksCreator, TracksEditor, RequiresApproval, SoftDeletes;

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
