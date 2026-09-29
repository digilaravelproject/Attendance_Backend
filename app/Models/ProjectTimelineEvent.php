<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTimelineEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'event', 'description', 'actor_id', 'event_at',
    ];

    protected function casts(): array
    {
        return ['event_at' => 'datetime'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
