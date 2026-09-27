<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait TracksCreator
{
    protected static function bootTracksCreator(): void
    {
        static::creating(function ($record): void {
            if ($record->user_id === null && Auth::check()) {
                $record->user_id = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
