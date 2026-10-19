<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->normalizeCategory($request);
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', Rule::in(LeadStatus::CATEGORIES)],
        ]);

        $query = LeadStatus::query()->withCount('leads');
        if (! empty($validated['q'])) {
            $term = '%'.trim($validated['q']).'%';
            $query->where(fn ($query) => $query
                ->where('name', 'like', $term)
                ->orWhere('description', 'like', $term));
        }
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $statuses = $query->orderBy('order_position')->orderBy('id')->get();

        return response()->json([
            'status' => true,
            'message' => 'Lead statuses retrieved successfully.',
            'total' => $statuses->count(),
            'data' => $statuses->map(fn (LeadStatus $status) => $this->data($status))->all(),
        ]);
    }

    public function total(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Lead status totals retrieved successfully.',
            'data' => [
                'total_statuses' => LeadStatus::count(),
                'total_leads' => Lead::count(),
                'by_category' => LeadStatus::query()
                    ->selectRaw('category, COUNT(*) as total')
                    ->groupBy('category')
                    ->pluck('total', 'category'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeInput($request);
        $request->merge(['slug' => $this->slug((string) $request->input('name'))]);
        $validated = $request->validate($this->rules());
        $validated['order_position'] ??= ((int) LeadStatus::max('order_position')) + 1;

        $status = LeadStatus::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Lead status created successfully.',
            'data' => $this->data($status->loadCount('leads')),
        ], 201);
    }

    public function show(LeadStatus $leadStatus): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Lead status details retrieved successfully.',
            'data' => $this->data($leadStatus->loadCount('leads')),
        ]);
    }

    public function update(Request $request, LeadStatus $leadStatus): JsonResponse
    {
        $this->normalizeInput($request);
        if ($request->filled('name') && ! $leadStatus->is_system) {
            $request->merge(['slug' => $this->slug((string) $request->input('name'))]);
        }
        $validated = $request->validate($this->rules($leadStatus));
        $oldSlug = $leadStatus->slug;

        DB::transaction(function () use ($leadStatus, $validated, $oldSlug) {
            $leadStatus->update($validated);
            if ($oldSlug !== $leadStatus->slug) {
                Lead::where('status', $oldSlug)->update(['status' => $leadStatus->slug]);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead status updated successfully.',
            'data' => $this->data($leadStatus->fresh()->loadCount('leads')),
        ]);
    }

    public function destroy(Request $request, LeadStatus $leadStatus): JsonResponse
    {
        if ($leadStatus->is_system) {
            return response()->json([
                'status' => false,
                'message' => 'The system default lead status cannot be deleted.',
            ], 409);
        }

        $validated = $request->validate([
            'replacement_status_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('lead_statuses', 'id')->where(fn ($query) => $query->where('id', '!=', $leadStatus->id)),
            ],
        ]);
        $leadsCount = $leadStatus->leads()->count();

        if ($leadsCount > 0 && empty($validated['replacement_status_id'])) {
            return response()->json([
                'status' => false,
                'message' => 'This status is assigned to leads. Provide replacement_status_id to move them before deletion.',
                'leads_count' => $leadsCount,
            ], 409);
        }

        DB::transaction(function () use ($leadStatus, $validated) {
            if (! empty($validated['replacement_status_id'])) {
                $replacement = LeadStatus::findOrFail($validated['replacement_status_id']);
                $leadStatus->leads()->update(['status' => $replacement->slug]);
            }
            $leadStatus->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Lead status deleted successfully.',
        ]);
    }

    private function rules(?LeadStatus $leadStatus = null): array
    {
        $presence = $leadStatus ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255', Rule::unique('lead_statuses', 'name')->ignore($leadStatus?->id)],
            'slug' => [$presence, 'string', 'max:40', Rule::unique('lead_statuses', 'slug')->ignore($leadStatus?->id)],
            'category' => [$presence, Rule::in(LeadStatus::CATEGORIES)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'badge_color' => [$presence, 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'order_position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function normalizeInput(Request $request): void
    {
        $normalized = [];
        if ($request->has('name') || $request->has('status_name')) {
            $normalized['name'] = $request->input('name', $request->input('status_name'));
        }
        if ($request->has('description') || $request->has('guideline')) {
            $normalized['description'] = $request->input('description', $request->input('guideline'));
        }
        if ($request->has('badge_color') || $request->has('tag_color')) {
            $normalized['badge_color'] = strtoupper((string) $request->input('badge_color', $request->input('tag_color')));
        }
        $request->merge($normalized);
        $this->normalizeCategory($request);
    }

    private function normalizeCategory(Request $request): void
    {
        if (! $request->filled('category')) {
            return;
        }

        $category = Str::of((string) $request->input('category'))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
        $request->merge(['category' => $category === 'active_lead_in_negotiation' ? 'active_lead' : $category]);
    }

    private function slug(string $name): string
    {
        return Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(40, '')->value();
    }

    private function data(LeadStatus $status): array
    {
        return [
            'id' => $status->id,
            'name' => $status->name,
            'slug' => $status->slug,
            'category' => $status->category,
            'category_label' => LeadStatus::CATEGORY_LABELS[$status->category] ?? Str::headline($status->category),
            'description' => $status->description,
            'badge_color' => $status->badge_color,
            'order_position' => $status->order_position,
            'is_system' => $status->is_system,
            'leads_count' => $status->leads_count ?? $status->leads()->count(),
            'created_at' => $status->created_at?->toIso8601String(),
            'updated_at' => $status->updated_at?->toIso8601String(),
        ];
    }
}
