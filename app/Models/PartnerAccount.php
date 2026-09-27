<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerAccount extends Model
{
    use TracksCreator;

    protected $fillable = ['name', 'key'];

    public function transactions(): HasMany
    {
        return $this->hasMany(PartnerTransaction::class);
    }
}
