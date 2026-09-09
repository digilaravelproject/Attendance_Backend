<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignedShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'user_id',
        'date',
        'assignment_type',
        'assigned_by',
        'status',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
