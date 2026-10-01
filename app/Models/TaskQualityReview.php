<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskQualityReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'employee_id',
        'reviewer_id',
        'deliverable_accuracy',
        'deadline_adherence',
        'defect_prevention',
        'collaboration',
        'feedback',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'deliverable_accuracy' => 'integer',
            'deadline_adherence' => 'integer',
            'defect_prevention' => 'integer',
            'collaboration' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
