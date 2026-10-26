<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'mobile_number', 'website', 'address', 'status',
        'notes', 'lead_id', 'created_by',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function communications()
    {
        return $this->hasMany(ClientCommunication::class)->latest('communicated_at');
    }
}
