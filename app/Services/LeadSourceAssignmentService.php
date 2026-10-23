<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadSourceAssignmentService
{
    public function isActive(string $sourceKey): bool
    {
        return LeadSource::where('source_key', $sourceKey)->where('is_active', true)->exists();
    }

    public function assignNewLead(Lead $lead): ?User
    {
        if ($lead->assigned_to) {
            return $lead->assignedEmployee;
        }

        return DB::transaction(function () use ($lead) {
            $source = LeadSource::where('source_key', $lead->source)->lockForUpdate()->first();
            if (! $source || ! $source->is_active) {
                return null;
            }

            $employees = $source->employees()
                ->where(fn ($query) => $query->whereNull('users.status')->orWhereRaw('LOWER(users.status) = ?', ['active']))
                ->get(['users.id', 'users.employee_id', 'users.name']);
            if ($employees->isEmpty()) {
                return null;
            }

            $index = $source->allocation_strategy === 'primary'
                ? 0
                : $source->round_robin_cursor % $employees->count();
            $employee = $employees->values()->get($index);
            $lead->update(['assigned_to' => $employee->id]);

            if ($source->allocation_strategy === 'round_robin') {
                $source->update(['round_robin_cursor' => ($index + 1) % $employees->count()]);
            }

            $lead->histories()->create([
                'event_type' => 'auto_assigned',
                'title' => 'Auto-assigned to '.$employee->name,
                'description' => 'Lead assigned automatically by the '.$source->name.' source rule.',
                'metadata' => [
                    'lead_source_id' => $source->id,
                    'source_key' => $source->source_key,
                    'employee_id' => $employee->id,
                    'allocation_strategy' => $source->allocation_strategy,
                ],
                'occurred_at' => now(),
            ]);

            return $employee;
        });
    }
}
