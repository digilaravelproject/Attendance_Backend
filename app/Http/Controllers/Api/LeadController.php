<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\LeadSourceAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function __construct(private readonly LeadSourceAssignmentService $leadSources) {}

    public function index(Request $request): JsonResponse
    {
        $this->normalizeSourceFilter($request);
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', Rule::in([...Lead::SOURCES, 'all_channels'])],
            'assignment' => ['sometimes', 'nullable', Rule::in(['all', 'assigned', 'unassigned'])],
            'status' => ['sometimes', 'nullable', Rule::exists('lead_statuses', 'slug')],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Lead::query()->with('assignedEmployee:id,employee_id,name,email,mobile_number,designation,avatar');
        $this->applyFilters($query, $validated);
        $paginator = $query->latest('source_created_at')->latest('id')
            ->paginate($validated['per_page'] ?? 20)->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Leads retrieved successfully.',
            'summary' => $this->summary(),
            'data' => collect($paginator->items())->map(fn (Lead $lead) => $this->leadData($lead))->all(),
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

    public function salesEmployees(): JsonResponse
    {
        $employees = $this->salesEmployeeQuery()
            ->withCount([
                'assignedLeads',
                'assignedLeads as deals_closed_count' => fn (Builder $query) => $query->where('status', 'converted'),
            ])
            ->orderBy('name')
            ->get(['id', 'employee_id', 'name', 'email', 'mobile_number', 'designation', 'avatar', 'status']);

        return response()->json([
            'status' => true,
            'message' => 'Sales employees retrieved successfully.',
            'data' => $employees,
        ]);
    }

    public function salesEmployeeDetails(Request $request, User $employee): JsonResponse
    {
        if (! $this->salesEmployeeQuery()->whereKey($employee->id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Active sales employee not found.',
            ], 404);
        }
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $base = Lead::where('assigned_to', $employee->id);
        $paginator = (clone $base)->with([
            'latestHistory.actor:id,employee_id,name',
            'createdBy:id,employee_id,name,role',
        ])->latest('updated_at')->latest('id')->paginate($validated['per_page'] ?? 20);
        $histories = LeadHistory::whereIn('lead_id', (clone $base)->select('id'))
            ->whereIn('event_type', ['call', 'call_completed'])
            ->get();
        $totalTalkSeconds = (int) $histories->sum(fn ($history) => (int) ($history->metadata['duration_seconds'] ?? 0));
        $calls = $histories->filter(fn ($history) => (int) ($history->metadata['duration_seconds'] ?? 0) > 0)->count();
        $revenue = (float) (clone $base)->where('status', 'converted')->sum('estimated_value');
        $target = (float) ($employee->sales_target ?? 0);

        return response()->json([
            'status' => true,
            'message' => 'Sales employee lead details retrieved successfully.',
            'data' => [
                'employee' => $employee->only(['id', 'employee_id', 'name', 'email', 'mobile_number', 'designation', 'avatar', 'status']),
                'summary' => [
                    'revenue' => $revenue,
                    'target' => $target,
                    'target_achieved_percentage' => $target > 0 ? round(($revenue / $target) * 100, 2) : 0,
                    'average_talk_seconds' => $calls > 0 ? (int) round($totalTalkSeconds / $calls) : 0,
                    'total_talk_seconds' => $totalTalkSeconds,
                    'admin_assigned_leads' => (clone $base)->where(fn ($query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $employee->id))->count(),
                    'self_brought_leads' => (clone $base)->where('created_by', $employee->id)->count(),
                    'active_leads' => (clone $base)->whereNotIn('status', ['converted', 'lost', 'unreachable', 'wrong_number'])->count(),
                    'total_leads' => (clone $base)->count(),
                ],
                'leads' => collect($paginator->items())->map(function (Lead $lead) use ($employee) {
                    $item = $this->leadData($lead);
                    $item['assignment_origin'] = $lead->created_by === $employee->id ? 'self_brought' : 'admin_assigned';
                    $item['recent_activity'] = $lead->latestHistory ? [
                        'id' => $lead->latestHistory->id,
                        'event_type' => $lead->latestHistory->event_type,
                        'title' => $lead->latestHistory->title,
                        'description' => $lead->latestHistory->description,
                        'occurred_at' => $lead->latestHistory->occurred_at?->toIso8601String(),
                    ] : null;

                    return $item;
                })->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function assign(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $employee = $this->salesEmployeeQuery()->find($validated['employee_id']);
        if (! $employee) {
            return response()->json([
                'status' => false,
                'message' => 'The selected user is not an active sales employee.',
            ], 422);
        }

        $previousEmployee = $lead->assignedEmployee;
        DB::transaction(function () use ($request, $lead, $employee, $previousEmployee, $validated) {
            $lead->update(['assigned_to' => $employee->id]);
            $lead->histories()->create([
                'actor_id' => $request->user()->id,
                'event_type' => $previousEmployee ? 'reassigned' : 'assigned',
                'title' => ($previousEmployee ? 'Re-assigned to ' : 'Assigned to ').$employee->name,
                'description' => $validated['notes'] ?? (
                    $previousEmployee
                        ? "Lead reassigned from {$previousEmployee->name} to {$employee->name}."
                        : "Lead assigned to {$employee->name}."
                ),
                'metadata' => [
                    'previous_employee_id' => $previousEmployee?->id,
                    'employee_id' => $employee->id,
                ],
                'occurred_at' => now(),
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead assigned successfully.',
            'data' => $this->leadData($lead->fresh('assignedEmployee')),
        ]);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load([
            'assignedEmployee:id,employee_id,name,email,mobile_number,designation,avatar',
            'histories.actor:id,employee_id,name',
        ]);

        $data = $this->leadData($lead);
        $data['source_payload'] = $lead->source_payload;
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

    public function store(Request $request): JsonResponse
    {
        $this->normalizeInput($request);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['required', Rule::in(Lead::SOURCES)],
            'status' => ['required', Rule::exists('lead_statuses', 'slug')],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'requirement' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'expected_closing_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if (! $this->leadSources->isActive($validated['source'])) {
            return response()->json([
                'status' => false,
                'message' => Str::headline($validated['source']).' lead source is inactive. The lead was not inserted.',
                'source_key' => $validated['source'],
                'is_active' => false,
            ], 409);
        }

        $employee = isset($validated['assigned_to'])
            ? $this->salesEmployeeQuery()->find($validated['assigned_to'])
            : null;
        if (isset($validated['assigned_to']) && ! $employee) {
            return response()->json([
                'status' => false,
                'message' => 'The selected user is not an active sales employee.',
            ], 422);
        }

        $lead = DB::transaction(function () use ($request, $validated, $employee) {
            $lead = Lead::create([...$validated, 'created_by' => $request->user()->id]);
            $lead->update(['lead_code' => 'LD-'.str_pad((string) (1000 + $lead->id), 4, '0', STR_PAD_LEFT)]);
            $lead->histories()->create([
                'actor_id' => $request->user()->id,
                'event_type' => 'captured',
                'title' => 'Lead captured via '.Str::headline($lead->source),
                'description' => $lead->notes ?: 'Lead created manually.',
                'occurred_at' => now(),
            ]);
            if ($employee) {
                $lead->histories()->create([
                    'actor_id' => $request->user()->id,
                    'event_type' => 'assigned',
                    'title' => 'Assigned to '.$employee->name,
                    'description' => 'Lead assigned during creation.',
                    'metadata' => ['employee_id' => $employee->id],
                    'occurred_at' => now(),
                ]);
            } elseif (! $this->leadSources->assignNewLead($lead)) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['Assign an employee directly or configure an active employee on this lead source.'],
                ]);
            }

            return $lead;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead created successfully.',
            'data' => $this->leadData($lead->load('assignedEmployee')),
        ], 201);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['q'])).'%';
            $query->where(function (Builder $query) use ($term) {
                $query->where('lead_code', 'like', $term)
                    ->orWhere('full_name', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term)
                    ->orWhere('requirement', 'like', $term);
            });
        }
        if (! empty($filters['source']) && $filters['source'] !== 'all_channels') {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if (($filters['assignment'] ?? null) === 'assigned') {
            $query->whereNotNull('assigned_to');
        } elseif (($filters['assignment'] ?? null) === 'unassigned') {
            $query->whereNull('assigned_to');
        }
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

    private function summary(): array
    {
        return [
            'all' => Lead::count(),
            'unassigned' => Lead::whereNull('assigned_to')->count(),
            'assigned' => Lead::whereNotNull('assigned_to')->count(),
            'by_source' => Lead::query()->selectRaw('source, COUNT(*) as total')
                ->groupBy('source')->pluck('total', 'source'),
            'by_status' => Lead::query()->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
        ];
    }

    private function normalizeInput(Request $request): void
    {
        $source = $request->input('source', $request->input('lead_source'));
        $status = $request->input('status', $request->input('lead_status'));
        $request->merge([
            'full_name' => $request->input('full_name', $request->input('name')),
            'mobile_number' => $request->input('mobile_number', $request->input('phone')),
            'assigned_to' => $request->input('assigned_to', $request->input('assign_to')),
            'source' => is_string($source) ? Str::of($source)->trim()->lower()->replace(['-', ' '], '_')->replaceMatches('/_+/', '_')->value() : $source,
            'status' => is_string($status) ? Str::of($status)->trim()->lower()->replace(['-', ' '], '_')->replaceMatches('/_+/', '_')->value() : $status,
        ]);
    }

    private function normalizeSourceFilter(Request $request): void
    {
        if (! $request->filled('source')) {
            return;
        }

        $source = Str::of((string) $request->input('source'))
            ->trim()->lower()->replace(['-', ' '], '_')->replaceMatches('/_+/', '_')->value();
        $request->merge(['source' => $source === 'all' ? 'all_channels' : $source]);
    }

    private function leadData(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'lead_code' => $lead->lead_code,
            'source' => $lead->source,
            'full_name' => $lead->full_name,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'mobile_number' => $lead->mobile_number,
            'designation' => $lead->designation,
            'requirement' => $lead->requirement,
            'status' => $lead->status,
            'assigned_to' => $lead->assigned_to,
            'assigned_employee' => $lead->relationLoaded('assignedEmployee') ? $lead->assignedEmployee : null,
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
