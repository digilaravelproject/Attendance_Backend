<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'from_user_id',
        'to_user_id',
        'handed_over_by',
        'reason',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function handedOverBy()
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }
}
