<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait TracksEditor
{
    protected static function bootTracksEditor(): void
    {
        static::updating(function ($record): void {
            if (Auth::check()) {
                $record->updated_by = Auth::id();
            }
        });
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
