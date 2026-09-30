<?php

namespace App\Models;

use App\Models\Concerns\{RequiresApproval, TracksCreator, TracksEditor};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleEntry extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor, RequiresApproval;

    protected $fillable = ['reference_user_id', 'from_destination', 'to_destination', 'entry_date', 'amount_rupees'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount_rupees' => 'decimal:2'];
    }

    public function referenceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reference_user_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(VehicleEntryItem::class);
    }
}
