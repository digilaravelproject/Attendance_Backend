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
        'designation',
        'designation_id',
        'employee_id',
        'date_of_joining',
        'monthly_salary',
        'skills',
        'address',
        'avatar',
        'status',
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
            'monthly_salary' => 'decimal:2',
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
}
