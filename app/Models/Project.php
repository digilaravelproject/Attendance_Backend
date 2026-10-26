<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'client_id', 'name', 'description', 'deliverables',
        'special_instructions', 'category', 'priority', 'start_date', 'end_date',
        'working_days', 'agreed_value', 'status', 'progress', 'created_by',
        'project_manager_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'progress' => 'integer',
            'working_days' => 'integer',
            'agreed_value' => 'decimal:2',
        ];
    }

    public function members()
    {
        return $this->belongsToMany(User::class)->withPivot('assigned_by')->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function files()
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function timelineEvents()
    {
        return $this->hasMany(ProjectTimelineEvent::class)->latest('event_at');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->latest();
    }
}
