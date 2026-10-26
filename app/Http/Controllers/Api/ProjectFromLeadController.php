<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Services\WorkingDayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectFromLeadController extends Controller
{
    public function __invoke(Request $request, Lead $lead, WorkingDayService $workingDays): JsonResponse
    {
        $user = $request->user();
        if (strtolower((string) $user->role) !== 'admin' && $lead->assigned_to !== $user->id) {
            return response()->json(['status' => false, 'message' => 'Lead not found in your assigned leads.'], 404);
        }
        if ($lead->status !== 'converted') {
            return response()->json(['status' => false, 'message' => 'Only a converted lead can be used to create a project.'], 422);
        }
        if ($lead->project()->exists()) {
            return response()->json(['status' => false, 'message' => 'A project already exists for this converted lead.'], 409);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'priority' => ['sometimes', Rule::in(['Standard', 'High Priority', 'Urgent'])],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'deliverables' => ['required', 'string', 'max:10000'],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'project_manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'agreed_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

        $project = DB::transaction(function () use ($request, $lead, $validated, $workingDays) {
            $client = Client::updateOrCreate(
                ['lead_id' => $lead->id],
                [
                    'name' => $lead->company_name ?: $lead->full_name,
                    'email' => $lead->email,
                    'mobile_number' => $lead->mobile_number,
                    'address' => $lead->location,
                    'notes' => $lead->notes,
                    'status' => 'Active',
                    'created_by' => $request->user()->id,
                ]
            );
            $dayData = $workingDays->calculate(
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date'])
            );
            $project = Project::create([
                ...collect($validated)->except(['employee_ids', 'files'])->all(),
                'lead_id' => $lead->id,
                'client_id' => $client->id,
                'description' => $validated['deliverables'],
                'working_days' => $dayData['working_days'],
                'agreed_value' => $validated['agreed_value'] ?? $lead->estimated_value,
                'status' => 'Not Started',
                'progress' => 0,
                'created_by' => $request->user()->id,
            ]);
            $memberIds = collect($validated['employee_ids'] ?? [])
                ->when($validated['project_manager_id'] ?? null, fn ($ids) => $ids->push($validated['project_manager_id']))
                ->unique()->values()->all();
            $project->members()->syncWithPivotValues($memberIds, ['assigned_by' => $request->user()->id]);
            foreach ($request->file('files', []) as $file) {
                $project->files()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $file->store('project-files/'.$project->id, 'public'),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
            }
            $project->timelineEvents()->create([
                'event' => 'Converted Lead Project Created',
                'description' => 'Project created from converted lead '.$lead->lead_code.'.',
                'actor_id' => $request->user()->id,
                'event_at' => now(),
            ]);

            return $project;
        });

        return response()->json([
            'status' => true,
            'message' => 'Project created from converted lead successfully.',
            'data' => $this->data($project),
        ], 201);
    }

    private function data(Project $project): array
    {
        $project->load(['lead', 'client', 'projectManager', 'members', 'files']);

        return [
            ...$project->toArray(),
            'files' => $project->files->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'url' => Storage::disk('public')->url($file->path),
            ]),
        ];
    }
}
