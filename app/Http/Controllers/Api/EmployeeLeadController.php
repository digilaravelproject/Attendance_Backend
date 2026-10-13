<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeLeadController extends Controller
{
    private const REASSIGNMENT_REASONS = [
        'branch_location_transfer',
        'language_preference',
        'enterprise_specialist',
        'workload_balancing',
        'client_requested_callback',
        'special_deal_term_requirement',
    ];

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensureSalesEmployee($request->user())) {
            return $response;
        }

        $this->normalizeStatusFilter($request);
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(Lead::STATUSES)],
            'source' => ['sometimes', 'nullable', Rule::in(Lead::SOURCES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $base = Lead::query()->where('assigned_to', $request->user()->id);
        $query = (clone $base)->with([
            'assignedEmployee:id,employee_id,name,email,mobile_number,designation,avatar',
            'createdBy:id,employee_id,name,role',
            'latestAssignment.actor:id,employee_id,name,role',
        ]);

        if (! empty($validated['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($validated['q'])).'%';
            $query->where(function (Builder $query) use ($term) {
                $query->where('lead_code', 'like', $term)
                    ->orWhere('full_name', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhere('requirement', 'like', $term);
            });
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        $paginator = $query->latest('updated_at')->latest('id')
            ->paginate($validated['per_page'] ?? 20)->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Assigned leads retrieved successfully.',
            'summary' => [
                'all' => (clone $base)->count(),
                'interested' => (clone $base)->where('status', 'interested')->count(),
                'follow_up' => (clone $base)->where('status', 'follow_up')->count(),
                'pending_call' => (clone $base)->where('status', 'pending_call')->count(),
                'converted' => (clone $base)->where('status', 'converted')->count(),
                'unreachable' => (clone $base)->where('status', 'unreachable')->count(),
                'wrong_number' => (clone $base)->where('status', 'wrong_number')->count(),
                'converted_revenue' => (float) (clone $base)->where('status', 'converted')->sum('estimated_value'),
            ],
            'data' => collect($paginator->items())
                ->map(fn (Lead $lead) => $this->leadData($lead, $request->user()))
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:255']]);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureSalesEmployee($request->user())) {
            return $response;
        }

        $this->normalizeInput($request);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['required', Rule::in(Lead::SOURCES)],
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'requirement' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'expected_closing_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $assignedEmployee = $this->salesEmployeeQuery()->find($validated['assigned_to']);
        if (! $assignedEmployee) {
            return $this->invalidSalesEmployee();
        }

        $lead = DB::transaction(function () use ($request, $validated, $assignedEmployee) {
            $lead = Lead::create([
                ...$validated,
                'created_by' => $request->user()->id,
                'source_created_at' => now(),
            ]);
            $lead->update(['lead_code' => 'TC-'.str_pad((string) (200 + $lead->id), 3, '0', STR_PAD_LEFT)]);
            $lead->histories()->create([
                'actor_id' => $request->user()->id,
                'event_type' => 'captured',
                'title' => 'Lead added by '.$request->user()->name,
                'description' => $lead->notes ?: 'Lead added from the employee CRM.',
                'metadata' => ['source' => $lead->source],
                'occurred_at' => now(),
            ]);
            $lead->histories()->create([
                'actor_id' => $request->user()->id,
                'event_type' => 'assigned',
                'title' => 'Assigned to '.$assignedEmployee->name,
                'description' => $assignedEmployee->is($request->user())
                    ? 'Self-assigned during lead creation.'
                    : 'Assigned to a sales colleague during lead creation.',
                'metadata' => ['employee_id' => $assignedEmployee->id],
                'occurred_at' => now(),
            ]);

            return $lead;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead created successfully.',
            'data' => $this->leadData(
                $lead->load(['assignedEmployee', 'createdBy', 'latestAssignment.actor']),
                $request->user()
            ),
        ], 201);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        if ($response = $this->ensureSalesEmployee($request->user())) {
            return $response;
        }
        if ($response = $this->ensureOwner($lead, $request->user())) {
            return $response;
        }

        $lead->load([
            'assignedEmployee:id,employee_id,name,email,mobile_number,designation,avatar',
            'createdBy:id,employee_id,name,role',
            'latestAssignment.actor:id,employee_id,name,role',
            'histories.actor:id,employee_id,name',
        ]);
        $data = $this->leadData($lead, $request->user());
        $data['history'] = $lead->histories->map(fn ($history) => [
            'id' => $history->id,
            'event_type' => $history->event_type,
            'title' => $history->title,
            'description' => $history->description,
            'actor' => $history->actor ? [
                'id' => $history->actor->id,
                'employee_id' => $history->actor->employee_id,
                'name' => $history->actor->name,
            ] : null,
            'actor_name' => $history->actor?->name ?? 'System Auto',
            'metadata' => $history->metadata,
            'occurred_at' => $history->occurred_at?->toIso8601String(),
        ])->all();

        return response()->json([
            'status' => true,
            'message' => 'Lead details retrieved successfully.',
            'data' => $data,
        ]);
    }

    public function salesEmployees(Request $request): JsonResponse
    {
        if ($response = $this->ensureSalesEmployee($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $query = $this->salesEmployeeQuery()
            ->withCount([
                'assignedLeads',
                'assignedLeads as deals_closed_count' => fn (Builder $query) => $query->where('status', 'converted'),
            ]);
        if (! empty($validated['q'])) {
            $term = '%'.trim($validated['q']).'%';
            $query->where(function (Builder $query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('employee_id', 'like', $term)
                    ->orWhere('designation', 'like', $term);
            });
        }

        return response()->json([
            'status' => true,
            'message' => 'Sales employees retrieved successfully.',
            'data' => $query->orderBy('name')
                ->get(['id', 'employee_id', 'name', 'email', 'mobile_number', 'designation', 'avatar', 'status']),
        ]);
    }

    public function assign(Request $request, Lead $lead): JsonResponse
    {
        if ($response = $this->ensureSalesEmployee($request->user())) {
            return $response;
        }
        if ($response = $this->ensureOwner($lead, $request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', Rule::in(self::REASSIGNMENT_REASONS)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $employee = $this->salesEmployeeQuery()->find($validated['employee_id']);
        if (! $employee) {
            return $this->invalidSalesEmployee();
        }
        if ($employee->id === $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'The lead is already assigned to you.',
            ], 422);
        }

        DB::transaction(function () use ($request, $lead, $employee, $validated) {
            $previousEmployee = $request->user();
            $lead->update(['assigned_to' => $employee->id]);
            $lead->histories()->create([
                'actor_id' => $previousEmployee->id,
                'event_type' => 'reassigned',
                'title' => 'Re-assigned to '.$employee->name,
                'description' => $validated['notes'] ?? 'Lead handed off to another sales employee.',
                'metadata' => [
                    'previous_employee_id' => $previousEmployee->id,
                    'employee_id' => $employee->id,
                    'reason' => $validated['reason'],
                    'reason_label' => Str::headline($validated['reason']),
                ],
                'occurred_at' => now(),
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead reassigned successfully.',
            'data' => $this->leadData(
                $lead->fresh(['assignedEmployee', 'createdBy', 'latestAssignment.actor']),
                $request->user()
            ),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        if ($response = $this->ensureSalesEmployee($request->user())) {
            return $response;
        }
        if ($response = $this->ensureOwner($lead, $request->user())) {
            return $response;
        }

        $this->normalizeInput($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'call_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        $note = $validated['call_note'] ?? $validated['notes'] ?? null;
        $oldStatus = $lead->status;

        DB::transaction(function () use ($request, $lead, $validated, $note, $oldStatus) {
            $lead->update(['status' => $validated['status']]);
            $lead->histories()->create([
                'actor_id' => $request->user()->id,
                'event_type' => 'status_changed',
                'title' => 'Status changed to '.Str::headline($validated['status']),
                'description' => $note,
                'metadata' => [
                    'old_status' => $oldStatus,
                    'new_status' => $validated['status'],
                ],
                'occurred_at' => now(),
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead status updated successfully.',
            'data' => $this->leadData(
                $lead->fresh(['assignedEmployee', 'createdBy', 'latestAssignment.actor']),
                $request->user()
            ),
        ]);
    }

    private function salesEmployeeQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereRaw('LOWER(role) IN (?, ?)', ['sale', 'sales'])
                    ->orWhereHas('roles', fn (Builder $role) => $role->whereRaw('LOWER(name) IN (?, ?)', ['sale', 'sales']))
                    ->orWhereRaw('LOWER(COALESCE(designation, ?)) LIKE ?', ['', '%sales%']);
            })
            ->where(function (Builder $query) {
                $query->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['active']);
            });
    }

    private function ensureSalesEmployee(User $user): ?JsonResponse
    {
        if (! $this->salesEmployeeQuery()->whereKey($user->id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. An active sales employee is required.',
            ], 403);
        }

        return null;
    }

    private function ensureOwner(Lead $lead, User $user): ?JsonResponse
    {
        if ($lead->assigned_to !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found in your assigned leads.',
            ], 404);
        }

        return null;
    }

    private function invalidSalesEmployee(): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'The selected user is not an active sales employee.',
        ], 422);
    }

    private function normalizeInput(Request $request): void
    {
        $source = $request->input('source', $request->input('lead_source'));
        $status = $request->input('status', $request->input('lead_status'));
        $request->merge([
            'full_name' => $request->input('full_name', $request->input('name')),
            'mobile_number' => $request->input('mobile_number', $request->input('phone')),
            'assigned_to' => $request->input('assigned_to', $request->input('assign_to')),
            'source' => is_string($source) ? $this->normalizeChoice($source) : $source,
            'status' => is_string($status) ? $this->normalizeChoice($status) : $status,
        ]);
    }

    private function normalizeStatusFilter(Request $request): void
    {
        if ($request->filled('status')) {
            $request->merge(['status' => $this->normalizeChoice((string) $request->input('status'))]);
        }
    }

    private function normalizeChoice(string $value): string
    {
        return Str::of($value)->trim()->lower()->replace(['-', ' '], '_')->replaceMatches('/_+/', '_')->value();
    }

    private function leadData(Lead $lead, User $viewer): array
    {
        $createdBy = $lead->relationLoaded('createdBy') ? $lead->createdBy : null;
        $assignmentActor = $lead->relationLoaded('latestAssignment') ? $lead->latestAssignment?->actor : null;
        $origin = $lead->created_by === $viewer->id
            ? 'self_brought'
            : (($assignmentActor?->role === 'admin' || $createdBy?->role === 'admin') ? 'admin_assigned' : 'transferred');

        return [
            'id' => $lead->id,
            'lead_code' => $lead->lead_code,
            'source' => $lead->source,
            'assignment_origin' => $origin,
            'full_name' => $lead->full_name,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'mobile_number' => $lead->mobile_number,
            'designation' => $lead->designation,
            'requirement' => $lead->requirement,
            'status' => $lead->status,
            'assigned_to' => $lead->assigned_to,
            'is_owner' => $lead->assigned_to === $viewer->id,
            'assigned_employee' => $lead->relationLoaded('assignedEmployee') ? $lead->assignedEmployee : null,
            'created_by' => $createdBy,
            'location' => $lead->location,
            'estimated_value' => $lead->estimated_value,
            'expected_closing_date' => $lead->expected_closing_date?->format('Y-m-d'),
            'notes' => $lead->notes,
            'source_created_at' => $lead->source_created_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];
    }
}
