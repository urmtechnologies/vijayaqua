<?php

namespace App\Models;

use App\Models\Concerns\{RequiresApproval, TracksCreator, TracksEditor};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use RequiresApproval, TracksCreator, TracksEditor;

    protected $fillable = ['employee_id', 'work_date', 'type', 'minutes', 'note'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'minutes' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }
}
