<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdvance extends Model
{
    use TracksCreator;

    protected $fillable = ['employee_id', 'month', 'paid_on', 'amount_paise', 'method', 'note'];

    protected function casts(): array
    {
        return ['month' => 'date', 'paid_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }
}
