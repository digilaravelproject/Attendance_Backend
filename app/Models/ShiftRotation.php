<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftRotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'frequency_cycle',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'shift_rotation_shifts', 'shift_rotation_id', 'shift_id')
            ->withTimestamps();
    }

    public function rotationUsers(): HasMany
    {
        return $this->hasMany(ShiftRotationUser::class, 'shift_rotation_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_rotation_users', 'shift_rotation_id', 'user_id')
            ->withPivot('shift_id')
            ->withTimestamps();
    }
}
