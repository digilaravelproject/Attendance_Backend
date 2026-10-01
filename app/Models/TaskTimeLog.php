<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'started_at',
        'stopped_at',
        'duration_seconds',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
