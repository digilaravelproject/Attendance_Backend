<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'type', 'subject', 'communicated_at', 'recipient',
        'description', 'attachment_path', 'attachment_name', 'created_by',
    ];

    protected function casts(): array
    {
        return ['communicated_at' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
