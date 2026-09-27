<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor;

    protected $fillable = ['expense_date', 'title', 'expense_category_id', 'amount_rupees', 'notes'];

    protected function casts(): array
    {
        return ['expense_date' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
