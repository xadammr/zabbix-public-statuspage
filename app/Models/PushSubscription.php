<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'endpoint_hash',
    'endpoint',
    'public_key',
    'auth_token',
    'content_encoding',
    'user_agent',
    'last_seen_at',
])]
class PushSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
