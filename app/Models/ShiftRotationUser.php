<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRotationUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_rotation_id',
        'user_id',
        'shift_id',
    ];

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(ShiftRotation::class, 'shift_rotation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
