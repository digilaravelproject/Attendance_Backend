<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTimelineEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }

        return $this->projectList($request, Project::query(), 'Projects retrieved successfully.');
    }

    public function total(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }

        $counts = Project::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'status' => true,
            'message' => 'Project totals retrieved successfully.',
            'data' => [
                'total_projects' => (int) $counts->sum(),
                'not_started' => (int) ($counts['Not Started'] ?? 0),
                'in_progress' => (int) ($counts['In Progress'] ?? 0),
                'completed' => (int) ($counts['Completed'] ?? 0),
                'on_hold' => (int) ($counts['On Hold'] ?? 0),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }
        $validator = Validator::make($request->all(), ['query' => ['required', 'string', 'max:255']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $request->merge(['search' => $request->input('query')]);

        return $this->projectList($request, Project::query(), 'Project search results retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }
        $this->normalizeEmployeeIds($request);
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $project = DB::transaction(function () use ($request, $validated) {
            $project = Project::create([
                ...collect($validated)->except(['employee_ids', 'files'])->all(),
                'progress' => $this->progress($validated),
                'created_by' => $request->user()->id,
            ]);
            $this->syncMembers($project, $validated['employee_ids'] ?? [], $request->user()->id);
            $this->storeFiles($project, $request);
            $this->record($project, 'Created', 'Project created and team members assigned.', $request->user()->id);

            return $project;
        });

        return response()->json([
            'status' => true,
            'message' => 'Project created successfully.',
            'data' => $this->details($project),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }
        $project = Project::find($id);
        if (! $project) {
            return $this->notFound();
        }

        return response()->json([
            'status' => true,
            'message' => 'Project details retrieved successfully.',
            'data' => $this->details($project),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }
        $project = Project::find($id);
        if (! $project) {
            return $this->notFound();
        }
        $this->normalizeEmployeeIds($request);
        if ($request->has('start_date') && ! $request->has('end_date')) {
            $request->merge(['end_date' => $project->end_date->toDateString()]);
        }
        if ($request->has('end_date') && ! $request->has('start_date')) {
            $request->merge(['start_date' => $project->start_date->toDateString()]);
        }
        $validator = Validator::make($request->all(), $this->rules(true, $project));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        DB::transaction(function () use ($request, $project, $validated) {
            $payload = collect($validated)->except(['employee_ids', 'files'])->all();
            if (array_key_exists('status', $validated) || array_key_exists('progress', $validated)) {
                $payload['progress'] = $this->progress([...$project->only(['status', 'progress']), ...$validated]);
            }
            if ($payload !== []) {
                $project->update($payload);
            }
            if (array_key_exists('employee_ids', $validated)) {
                $this->syncMembers($project, $validated['employee_ids'], $request->user()->id);
            }
            $this->storeFiles($project, $request);
            $this->record($project, 'Updated', 'Project details or team assignments were updated.', $request->user()->id);
        });

        return response()->json([
            'status' => true,
            'message' => 'Project updated successfully.',
            'data' => $this->details($project->fresh()),
        ]);
    }

    public function removeEmployee(Request $request, string $id, string $employeeId): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }
        $project = Project::find($id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $project->members()->whereKey($employeeId)->exists()) {
            return response()->json(['status' => false, 'message' => 'Employee is not assigned to this project.'], 404);
        }

        $employee = User::find($employeeId);
        $project->members()->detach($employeeId);
        $this->record($project, 'Member Removed', ($employee?->name ?? 'Employee').' was removed from the project.', $request->user()->id);

        return response()->json([
            'status' => true,
            'message' => 'Employee removed from project successfully.',
            'data' => $this->details($project->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return $this->forbidden();
        }
        $project = Project::with('files')->find($id);
        if (! $project) {
            return $this->notFound();
        }
        foreach ($project->files as $file) {
            Storage::disk('public')->delete($file->path);
        }
        $project->delete();

        return response()->json(['status' => true, 'message' => 'Project deleted successfully.']);
    }

    public function assigned(Request $request): JsonResponse
    {
        $query = Project::whereHas('members', fn ($member) => $member->whereKey($request->user()->id));

        return $this->projectList($request, $query, 'Assigned projects retrieved successfully.');
    }

    public function assignedShow(Request $request, string $id): JsonResponse
    {
        $project = Project::whereKey($id)
            ->whereHas('members', fn ($member) => $member->whereKey($request->user()->id))
            ->first();
        if (! $project) {
            return response()->json(['status' => false, 'message' => 'Assigned project not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Assigned project details retrieved successfully.',
            'data' => $this->details($project),
        ]);
    }

    private function projectList(Request $request, $query, string $message): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in(['all', 'All', 'Not Started', 'In Progress', 'Completed', 'On Hold'])],
            'category' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }
        if ($request->filled('status') && strtolower($request->input('status')) !== 'all') {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        $paginator = $query->with(['members:id,employee_id,name,email,designation,avatar,status'])
            ->withCount(['members', 'files'])->latest()->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => $message,
            'total' => $paginator->total(),
            'data' => collect($paginator->items())->map(fn (Project $project) => $this->summary($project)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function rules(bool $updating = false, ?Project $project = null): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'description' => [$presence, 'string', 'max:5000'],
            'category' => [$presence, 'string', 'max:100'],
            'start_date' => [$presence, 'date_format:Y-m-d'],
            'end_date' => [$presence, 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => [$presence, Rule::in(['Not Started', 'In Progress', 'Completed', 'On Hold'])],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'employee')->where('status', '!=', 'Inactive'))],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ];
    }

    private function normalizeEmployeeIds(Request $request): void
    {
        $ids = $request->input('employee_ids', $request->input('assigned_employee_ids'));
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : explode(',', $ids);
        }
        if ($ids !== null && ! is_array($ids)) {
            $ids = [$ids];
        }
        if (is_array($ids)) {
            $request->merge(['employee_ids' => array_values(array_unique(array_filter($ids, fn ($id) => $id !== null && $id !== '')))]);
        }
    }

    private function syncMembers(Project $project, array $employeeIds, int $assignedBy): void
    {
        $project->members()->syncWithPivotValues($employeeIds, ['assigned_by' => $assignedBy]);
    }

    private function storeFiles(Project $project, Request $request): void
    {
        foreach ($request->file('files', []) as $file) {
            $project->files()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('project-files/'.$project->id, 'public'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function record(Project $project, string $event, string $description, int $actorId): void
    {
        ProjectTimelineEvent::create([
            'project_id' => $project->id,
            'event' => $event,
            'description' => $description,
            'actor_id' => $actorId,
            'event_at' => now(),
        ]);
    }

    private function progress(array $data): int
    {
        if (($data['status'] ?? null) === 'Completed') {
            return 100;
        }

        return (int) ($data['progress'] ?? 0);
    }

    private function summary(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'category' => $project->category,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'status' => $project->status,
            'progress' => $project->progress,
            'members_count' => $project->members_count ?? ($project->relationLoaded('members') ? $project->members->count() : 0),
            'files_count' => $project->files_count ?? ($project->relationLoaded('files') ? $project->files->count() : 0),
            'team' => $project->members->map(fn (User $user) => $this->memberData($user))->values(),
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];
    }

    private function details(Project $project): array
    {
        $project->load([
            'creator:id,name,email,role',
            'members:id,employee_id,name,email,designation,avatar,status',
            'files.uploader:id,name,email',
            'timelineEvents.actor:id,name,email',
        ]);
        $files = $project->files->map(fn ($file) => [
            'id' => $file->id,
            'name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'url' => Storage::disk('public')->url($file->path),
            'uploaded_by' => $file->uploader,
            'created_at' => $file->created_at,
        ])->values();

        return [
            ...$this->summary($project),
            'created_by' => $project->creator,
            'overview' => [
                'description' => $project->description,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'status' => $project->status,
                'progress' => $project->progress,
                'status_track' => $this->statusTrack($project->status),
            ],
            'tasks' => [
                'is_static' => true,
                'message' => 'Task workflow will be added in a future release.',
                'summary' => ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'testing' => 0, 'to_do' => 0],
                'items' => [],
            ],
            'team' => $project->members->map(fn (User $user) => $this->memberData($user))->values(),
            'files' => $files,
            'timeline' => $project->timelineEvents->map(fn ($event) => [
                'id' => $event->id,
                'event' => $event->event,
                'description' => $event->description,
                'event_at' => $event->event_at,
                'actor' => $event->actor,
            ])->values(),
        ];
    }

    private function memberData(User $user): array
    {
        return [
            'id' => $user->id,
            'employee_id' => $user->employee_id,
            'name' => $user->name,
            'email' => $user->email,
            'designation' => $user->designation,
            'avatar' => $user->avatar,
            'status' => $user->status,
        ];
    }

    private function statusTrack(string $current): array
    {
        $order = ['Not Started', 'In Progress', 'Completed'];
        $currentIndex = array_search($current, $order, true);

        return collect($order)->map(fn ($status, $index) => [
            'status' => $status,
            'is_current' => $current === $status,
            'is_completed' => $current === 'Completed' || ($currentIndex !== false && $index < $currentIndex),
        ])->values()->all();
    }

    private function canManage(?User $user): bool
    {
        if (! $user || strtolower((string) $user->status) === 'inactive') {
            return false;
        }
        if (strtolower((string) $user->role) === 'admin') {
            return true;
        }

        return $user->designationDetails?->hierarchy_level === 'manager'
            || $user->roles()->where('name', 'like', '%manager%')->exists();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Forbidden. An administrator or manager is required.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Project not found.'], 404);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
