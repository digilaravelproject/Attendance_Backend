<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'owner_name',
        'mobile_number',
        'emergency_contact',
        'phone',
        'email',
        'password',
        'role',
        'department',
        'department_id',
        'team',
        'designation',
        'designation_id',
        'employee_id',
        'date_of_birth',
        'date_of_joining',
        'monthly_salary',
        'skills',
        'address',
        'avatar',
        'status',
        'reporting_manager_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_joining' => 'date:Y-m-d',
            'date_of_birth' => 'date:Y-m-d',
            'monthly_salary' => 'decimal:2',
            'sales_target_enabled' => 'boolean',
            'sales_target' => 'decimal:2',
            'incentive_commission_percent' => 'decimal:2',
            'skills' => 'array',
        ];
    }

    /**
     * Roles relationship.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function designationDetails()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function departmentDetails()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function assignedLeaveRequests()
    {
        return $this->belongsToMany(LeaveRequest::class, 'leave_request_assignees')->withTimestamps();
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class)->withPivot('assigned_by')->withTimestamps();
    }

    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(AdminDocument::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class);
    }

    public function assignedShift()
    {
        return $this->belongsTo(Shift::class, 'assigned_shift_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    public function performedNotifications()
    {
        return $this->hasMany(Notification::class, 'actor_id');
    }

    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_user')->withPivot('assigned_by')->withTimestamps();
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function reportingManager()
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(User::class, 'reporting_manager_id');
    }

    public function sentPerformanceMessages()
    {
        return $this->hasMany(PerformanceMessage::class, 'sender_id');
    }

    public function receivedPerformanceMessages()
    {
        return $this->hasMany(PerformanceMessage::class, 'receiver_id');
    }

    public function qualityReviews()
    {
        return $this->hasMany(TaskQualityReview::class, 'employee_id');
    }

    public function submittedQualityReviews()
    {
        return $this->hasMany(TaskQualityReview::class, 'reviewer_id');
    }
}
