<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskSubtask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'title',
        'is_completed',
        'assigned_to',
        'due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
