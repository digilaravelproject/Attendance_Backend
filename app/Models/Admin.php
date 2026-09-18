<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'company_name',
        'owner_name',
        'mobile_number',
        'phone',
        'email',
        'password',
        'department',
        'designation',
        'employee_id',
        'date_of_joining',
        'address',
        'avatar',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_joining' => 'date:Y-m-d',
        ];
    }

    public function documents()
    {
        return $this->hasMany(AdminDocument::class);
    }
}
