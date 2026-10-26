<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectFromLeadController extends Controller
{
    public function __invoke(Request $request, Lead $lead): JsonResponse
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
            'working_day' => ['required', 'integer', 'min:0'],
            'deliverables' => ['required', 'string', 'max:10000'],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'project_manager_id' => ['prohibited'],
            'employee_ids' => ['prohibited'],
            'agreed_value' => ['prohibited'],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

        $project = DB::transaction(function () use ($request, $lead, $validated) {
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
            $project = Project::create([
                ...collect($validated)->except(['working_day', 'files'])->all(),
                'lead_id' => $lead->id,
                'client_id' => $client->id,
                'description' => $validated['deliverables'],
                'working_days' => $validated['working_day'],
                'status' => 'Not Started',
                'progress' => 0,
                'created_by' => $request->user()->id,
            ]);
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
            'working_day' => $project->working_days,
            'files' => $project->files->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'url' => Storage::disk('public')->url($file->path),
            ]),
        ];
    }
}
