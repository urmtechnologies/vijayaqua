<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use App\Models\Concerns\TracksCreator;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerTransaction extends Model
{
    use SoftDeletes, TracksCreator, TracksEditor, RequiresApproval;

    protected $fillable = ['partner_account_id', 'transaction_date', 'type', 'amount_rupees', 'note'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerAccount::class, 'partner_account_id');
    }
}
