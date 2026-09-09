<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'shift_type',
        'start_time',
        'end_time',
        'break_duration',
        'total_duration',
        'grace_time_late',
        'overtime_after',
        'description',
        'status',
    ];

    public function assignedShifts(): HasMany
    {
        return $this->hasMany(AssignedShift::class, 'shift_id');
    }

    public function rotationShifts(): HasMany
    {
        return $this->hasMany(ShiftRotationShift::class, 'shift_id');
    }
}
