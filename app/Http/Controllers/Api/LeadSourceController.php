<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['all', 'active', 'inactive'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $query = LeadSource::query()->with('employees:id,employee_id,name,email,designation,avatar,status')
            ->withCount(['leads', 'leads as unassigned_leads_count' => fn ($lead) => $lead->whereNull('assigned_to')]);
        if (($validated['status'] ?? 'all') !== 'all') {
            $query->where('is_active', $validated['status'] === 'active');
        }
        if (! empty($validated['search'])) {
            $term = '%'.trim($validated['search']).'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('source_key', 'like', $term));
        }
        $sources = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'message' => 'Lead sources retrieved successfully.',
            'summary' => [
                'total' => $sources->count(),
                'active' => $sources->where('is_active', true)->count(),
                'inactive' => $sources->where('is_active', false)->count(),
                'unassigned_leads' => $sources->sum('unassigned_leads_count'),
            ],
            'data' => $sources->map(fn (LeadSource $source) => $this->data($source))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalize($request);
        $validated = $request->validate($this->rules());
        $existing = LeadSource::where('source_key', $validated['source_key'])->first();
        if ($existing?->is_configured) {
            return response()->json([
                'status' => false,
                'message' => 'A lead source with this source key already exists.',
            ], 409);
        }

        $source = DB::transaction(function () use ($request, $validated, $existing) {
            $source = $existing ?? new LeadSource;
            $source->fill([
                ...collect($validated)->except('employee_ids')->all(),
                'is_configured' => true,
                'created_by' => $source->created_by ?? $request->user()?->id,
            ])->save();
            $this->syncEmployees($source, $validated['employee_ids']);

            return $source;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead source created successfully.',
            'data' => $this->loadData($source),
        ], 201);
    }

    public function show(LeadSource $leadSource): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Lead source retrieved successfully.',
            'data' => $this->loadData($leadSource),
        ]);
    }

    public function update(Request $request, LeadSource $leadSource): JsonResponse
    {
        $this->normalize($request, $leadSource);
        $validated = $request->validate($this->rules(true, $leadSource));

        DB::transaction(function () use ($leadSource, $validated) {
            $leadSource->update([
                ...collect($validated)->except('employee_ids')->all(),
                'is_configured' => true,
            ]);
            if (array_key_exists('employee_ids', $validated)) {
                $this->syncEmployees($leadSource, $validated['employee_ids']);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead source updated successfully.',
            'data' => $this->loadData($leadSource->fresh()),
        ]);
    }

    private function rules(bool $updating = false, ?LeadSource $source = null): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'source_key' => array_values(array_filter([
                $presence,
                'string',
                'max:60',
                'regex:/^[a-z0-9_]+$/',
                $updating ? Rule::unique('lead_sources', 'source_key')->ignore($source?->id) : null,
            ])),
            'allocation_strategy' => [$presence, Rule::in(['round_robin', 'primary'])],
            'employee_ids' => [$presence, 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    private function normalize(Request $request, ?LeadSource $source = null): void
    {
        $name = $request->input('name', $request->input('lead_source_name', $source?->name));
        $strategy = $request->input('allocation_strategy', $request->input('strategy', $source?->allocation_strategy));
        $sourceKey = $request->input('source_key', $request->input('slug'));
        if (! $sourceKey && is_string($name)) {
            $sourceKey = $this->knownSourceKey($name);
        }
        $normalized = [
            'name' => $name,
            'source_key' => is_string($sourceKey) ? Str::slug($sourceKey, '_') : $sourceKey,
            'allocation_strategy' => is_string($strategy) ? Str::slug($strategy, '_') : $strategy,
        ];
        if ($request->has('employee_ids') || $request->has('assigned_employee_ids')) {
            $normalized['employee_ids'] = $request->input('employee_ids', $request->input('assigned_employee_ids'));
        }
        $request->merge($normalized);
    }

    private function knownSourceKey(string $name): string
    {
        $value = Str::lower($name);

        return match (true) {
            str_contains($value, 'facebook') => 'facebook',
            str_contains($value, 'google') => 'google',
            str_contains($value, 'justdial'), str_contains($value, 'just dial') => 'justdial',
            str_contains($value, 'website') => 'website',
            str_contains($value, 'referral') => 'referral',
            default => Str::slug($name, '_'),
        };
    }

    private function syncEmployees(LeadSource $source, array $employeeIds): void
    {
        $employees = $this->salesEmployeeQuery()->whereKey($employeeIds)->pluck('id');
        if ($employees->count() !== count(array_unique($employeeIds))) {
            throw ValidationException::withMessages([
                'employee_ids' => ['Every assigned user must be an active sales employee.'],
            ]);
        }
        $source->employees()->sync($employees->values()->mapWithKeys(
            fn ($id, $position) => [$id => ['position' => $position]]
        )->all());
        $source->update(['round_robin_cursor' => 0]);
    }

    private function salesEmployeeQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereRaw('LOWER(role) IN (?, ?)', ['sale', 'sales'])
                    ->orWhereHas('roles', fn (Builder $role) => $role->whereRaw('LOWER(name) IN (?, ?)', ['sale', 'sales']))
                    ->orWhereRaw('LOWER(COALESCE(designation, ?)) LIKE ?', ['', '%sales%']);
            })
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['active']));
    }

    private function loadData(LeadSource $source): array
    {
        return $this->data($source->load('employees:id,employee_id,name,email,designation,avatar,status')
            ->loadCount(['leads', 'leads as unassigned_leads_count' => fn ($lead) => $lead->whereNull('assigned_to')]));
    }

    private function data(LeadSource $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'source_key' => $source->source_key,
            'allocation_strategy' => $source->allocation_strategy,
            'is_active' => $source->is_active,
            'is_configured' => $source->is_configured,
            'color' => $source->color,
            'leads_count' => (int) ($source->leads_count ?? 0),
            'unassigned_leads_count' => (int) ($source->unassigned_leads_count ?? 0),
            'employees' => $source->employees,
            'created_at' => $source->created_at,
            'updated_at' => $source->updated_at,
        ];
    }
}
