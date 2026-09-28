<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    protected $fillable = ['module', 'scope', 'can_view', 'can_create', 'can_edit', 'can_delete', 'can_invoice'];

    protected function casts(): array
    {
        return ['can_view' => 'boolean', 'can_create' => 'boolean', 'can_edit' => 'boolean', 'can_delete' => 'boolean', 'can_invoice' => 'boolean'];
    }
}
