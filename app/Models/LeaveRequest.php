<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'leave_type_id', 'from_date', 'to_date', 'total_days', 'reason',
        'contact_during_leave', 'attachment_name', 'attachment_path', 'status',
        'review_note', 'reviewed_by_user_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'total_days' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function actions()
    {
        return $this->hasMany(LeaveRequestAction::class)->orderByDesc('id');
    }
}
