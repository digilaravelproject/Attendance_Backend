<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'created_by', 'reminder_type', 'remind_at', 'note', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
