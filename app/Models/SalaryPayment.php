<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    use TracksCreator;

    protected $fillable = ['paid_on', 'amount_paise', 'method', 'note'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }
}
