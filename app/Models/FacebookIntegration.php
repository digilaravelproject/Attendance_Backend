<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookIntegration extends Model
{
    protected $fillable = [
        'state', 'client_state', 'client_id', 'client_secret', 'redirect_uri',
        'access_token', 'token_type', 'token_expires_at', 'page_id', 'page_name',
        'page_access_token', 'status', 'authorized_at', 'last_synced_at', 'last_error',
    ];

    protected $hidden = [
        'client_secret', 'access_token', 'page_access_token',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'page_access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
