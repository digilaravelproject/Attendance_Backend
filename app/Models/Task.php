<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_name',
        'description',
        'project_id',
        'category',
        'priority',
        'status',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_hours',
        'created_by',
        'total_logged_seconds',
        'is_timer_running',
        'timer_started_at',
        'testing_submitted_at',
        'testing_remarks',
        'testing_submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'total_logged_seconds' => 'integer',
            'is_timer_running' => 'boolean',
            'timer_started_at' => 'datetime',
            'testing_submitted_at' => 'datetime',
        ];
    }

    protected $appends = [
        'formatted_logged_time',
        'current_logged_seconds',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function subtasks()
    {
        return $this->hasMany(TaskSubtask::class, 'task_id');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'task_id')->latest();
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class, 'task_id');
    }

    public function timeLogs()
    {
        return $this->hasMany(TaskTimeLog::class, 'task_id')->latest();
    }

    public function testingSubmittedBy()
    {
        return $this->belongsTo(User::class, 'testing_submitted_by');
    }

    public function qualityReviews()
    {
        return $this->hasMany(TaskQualityReview::class);
    }

    public function handovers()
    {
        return $this->hasMany(TaskHandover::class)->latest();
    }

    /**
     * Compute real-time logged seconds including current running timer.
     */
    public function getCurrentLoggedSecondsAttribute()
    {
        $seconds = (int) $this->total_logged_seconds;
        if ($this->is_timer_running && $this->timer_started_at) {
            $seconds += Carbon::now()->diffInSeconds($this->timer_started_at);
        }

        return $seconds;
    }

    /**
     * Human-readable formatted time, e.g. "02h 15m 30s"
     */
    public function getFormattedLoggedTimeAttribute()
    {
        $totalSeconds = $this->getCurrentLoggedSecondsAttribute();
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
    }
}
