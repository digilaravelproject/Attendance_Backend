<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'category', 'start_date', 'end_date',
        'status', 'progress', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'progress' => 'integer',
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

    public function files()
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function timelineEvents()
    {
        return $this->hasMany(ProjectTimelineEvent::class)->latest('event_at');
    }
}
