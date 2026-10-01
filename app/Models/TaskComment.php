<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'comment',
        'attachment_path',
        'attachment_name',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getAttachmentUrlAttribute()
    {
        if (! $this->attachment_path) {
            return null;
        }

        if (filter_var($this->attachment_path, FILTER_VALIDATE_URL)) {
            return $this->attachment_path;
        }

        return url(Storage::url($this->attachment_path));
    }
}
