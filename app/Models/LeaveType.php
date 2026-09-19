<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'description', 'annual_allowance', 'is_paid',
        'requires_attachment', 'status',
    ];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'requires_attachment' => 'boolean'];
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
