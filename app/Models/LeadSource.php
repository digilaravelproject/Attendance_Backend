<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'source_key', 'allocation_strategy', 'is_active', 'is_configured',
        'round_robin_cursor', 'color', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_configured' => 'boolean',
            'round_robin_cursor' => 'integer',
        ];
    }

    public function employees()
    {
        return $this->belongsToMany(User::class, 'lead_source_user')
            ->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'source', 'source_key');
    }
}
