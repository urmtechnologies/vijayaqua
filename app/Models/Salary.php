<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Salary extends Model
{
    use TracksCreator;

    protected $fillable = ['employee_id', 'month', 'monthly_salary_paise', 'earned_paise'];

    protected function casts(): array
    {
        return ['month' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }
}
