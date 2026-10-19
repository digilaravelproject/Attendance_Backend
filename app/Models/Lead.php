<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    public const SOURCES = ['website', 'google_ads', 'facebook', 'justdial', 'referral'];

    public const STATUSES = [
        'new', 'interested', 'follow_up', 'pending_call', 'contacted', 'qualified',
        'proposal_sent', 'converted', 'unreachable', 'wrong_number', 'lost',
    ];

    protected $fillable = [
        'lead_code', 'source', 'external_id', 'full_name', 'company_name', 'email',
        'mobile_number', 'designation', 'requirement', 'status', 'assigned_to', 'created_by',
        'location', 'estimated_value', 'expected_closing_date', 'notes',
        'source_payload', 'source_created_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'expected_closing_date' => 'date:Y-m-d',
            'source_payload' => 'array',
            'source_created_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories()
    {
        return $this->hasMany(LeadHistory::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function latestAssignment()
    {
        return $this->hasOne(LeadHistory::class)
            ->whereIn('event_type', ['assigned', 'reassigned'])
            ->latestOfMany('occurred_at');
    }
}
