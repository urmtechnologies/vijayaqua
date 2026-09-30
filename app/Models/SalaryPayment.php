<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    use TracksCreator;

    protected $fillable = ['paid_on', 'amount_paise', 'method', 'note'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }
}
