<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientCommunication;
use App\Models\Project;
use App\Services\WorkingDayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->listing($request, false);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['query' => ['required', 'string', 'max:255']]);
        $request->merge(['q' => $request->input('query')]);

        return $this->listing($request, true);
    }

    public function store(Request $request): JsonResponse
    {
        $client = Client::create([
            ...$this->validateClient($request),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Client created successfully.',
            'data' => $this->clientData($client),
        ], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        return response()->json([
            'status' => true,
            'message' => 'Client details retrieved successfully.',
            'data' => $this->clientData($client, true),
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client, true);
        $client->update($this->validateClient($request, true));

        return response()->json([
            'status' => true,
            'message' => 'Client updated successfully.',
            'data' => $this->clientData($client->fresh(), true),
        ]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorizeClient($client, true);
        if ($client->projects()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Client cannot be deleted while projects are assigned. Remove or reassign the projects first.',
            ], 409);
        }
        foreach ($client->communications as $communication) {
            if ($communication->attachment_path) {
                Storage::disk('public')->delete($communication->attachment_path);
            }
        }
        $client->delete();

        return response()->json(['status' => true, 'message' => 'Client deleted successfully.']);
    }

    public function assignProject(Request $request, Client $client, WorkingDayService $workingDays): JsonResponse
    {
        $this->authorizeClient($client, true);
        $validated = $request->validate([
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'name' => ['required_without:project_id', 'string', 'max:255'],
            'description' => ['required_without:project_id', 'string', 'max:10000'],
            'category' => ['sometimes', 'string', 'max:100'],
            'start_date' => ['required_without:project_id', 'date_format:Y-m-d'],
            'end_date' => ['required_without:project_id', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'project_manager_id' => ['prohibited'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['Not Started', 'In Progress', 'Completed', 'On Hold', 'Active'])],
            'employee_ids' => ['prohibited'],
        ]);

        $project = DB::transaction(function () use ($request, $client, $validated, $workingDays) {
            if (! empty($validated['project_id'])) {
                $project = Project::findOrFail($validated['project_id']);
                if ($project->client_id && $project->client_id !== $client->id) {
                    abort(response()->json(['status' => false, 'message' => 'Project is already assigned to another client.'], 409));
                }
                $project->update(['client_id' => $client->id]);

                return $project;
            }

            $dayData = $workingDays->calculate(Carbon::parse($validated['start_date']), Carbon::parse($validated['end_date']));
            $project = Project::create([
                'client_id' => $client->id,
                'name' => $validated['name'],
                'description' => $validated['description'],
                'category' => $validated['category'] ?? 'General',
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'working_days' => $dayData['working_days'],
                'agreed_value' => $validated['estimated_value'] ?? null,
                'status' => ($validated['status'] ?? 'Not Started') === 'Active' ? 'In Progress' : ($validated['status'] ?? 'Not Started'),
                'progress' => 0,
                'created_by' => $request->user()->id,
            ]);
            $project->timelineEvents()->create([
                'event' => 'Assigned to Client',
                'description' => 'Project assigned to '.$client->name.'.',
                'actor_id' => $request->user()->id,
                'event_at' => now(),
            ]);

            return $project;
        });

        return response()->json([
            'status' => true,
            'message' => 'Project assigned to client successfully.',
            'data' => $this->projectData($project),
        ], empty($validated['project_id']) ? 201 : 200);
    }

    public function projects(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['all', 'active', 'completed', 'Not Started', 'In Progress', 'Completed', 'On Hold'])],
        ]);
        $query = $client->projects();
        $status = $validated['status'] ?? 'all';
        if (strtolower($status) === 'active') {
            $query->whereIn('status', ['Not Started', 'In Progress']);
        } elseif (strtolower($status) === 'completed') {
            $query->where('status', 'Completed');
        } elseif (strtolower($status) !== 'all') {
            $query->where('status', $status);
        }
        $projects = $query->with(['projectManager', 'members'])->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Assigned client projects retrieved successfully.',
            'client' => $client->only(['id', 'name', 'email', 'mobile_number', 'status']),
            'counts' => [
                'all' => $client->projects()->count(),
                'active' => $client->projects()->whereIn('status', ['Not Started', 'In Progress'])->count(),
                'completed' => $client->projects()->where('status', 'Completed')->count(),
            ],
            'total' => $projects->count(),
            'data' => $projects->map(fn (Project $project) => $this->projectData($project)),
        ]);
    }

    public function communications(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);
        $validated = $request->validate([
            'type' => ['sometimes', 'nullable', Rule::in(['all', 'Email', 'Call', 'Meeting', 'Note'])],
        ]);
        $query = $client->communications()->with('creator:id,name,email');
        if (! empty($validated['type']) && $validated['type'] !== 'all') {
            $query->where('type', $validated['type']);
        }
        $items = $query->get();

        return response()->json([
            'status' => true,
            'message' => 'Client communication history retrieved successfully.',
            'total' => $items->count(),
            'data' => $items->map(fn (ClientCommunication $item) => $this->communicationData($item)),
        ]);
    }

    public function storeCommunication(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);
        $validated = $this->validateCommunication($request);
        $communication = $client->communications()->create([
            ...collect($validated)->except('attachment')->all(),
            ...$this->storeAttachment($request),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Client communication added successfully.',
            'data' => $this->communicationData($communication),
        ], 201);
    }

    public function showCommunication(Client $client, ClientCommunication $communication): JsonResponse
    {
        $this->authorizeClient($client);
        $this->ensureCommunicationClient($client, $communication);

        return response()->json([
            'status' => true,
            'message' => 'Client communication retrieved successfully.',
            'data' => $this->communicationData($communication),
        ]);
    }

    public function updateCommunication(Request $request, Client $client, ClientCommunication $communication): JsonResponse
    {
        $this->authorizeClient($client);
        $this->ensureCommunicationClient($client, $communication);
        $this->authorizeCommunicationMutation($communication);
        $validated = $this->validateCommunication($request, true);
        $payload = collect($validated)->except('attachment')->all();
        if ($request->hasFile('attachment')) {
            if ($communication->attachment_path) {
                Storage::disk('public')->delete($communication->attachment_path);
            }
            $payload = [...$payload, ...$this->storeAttachment($request)];
        }
        $communication->update($payload);

        return response()->json([
            'status' => true,
            'message' => 'Client communication updated successfully.',
            'data' => $this->communicationData($communication->fresh()),
        ]);
    }

    public function destroyCommunication(Client $client, ClientCommunication $communication): JsonResponse
    {
        $this->authorizeClient($client);
        $this->ensureCommunicationClient($client, $communication);
        $this->authorizeCommunicationMutation($communication);
        if ($communication->attachment_path) {
            Storage::disk('public')->delete($communication->attachment_path);
        }
        $communication->delete();

        return response()->json(['status' => true, 'message' => 'Client communication deleted successfully.']);
    }

    private function listing(Request $request, bool $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => [$search ? 'required' : 'sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['all', 'Active', 'Inactive'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $visible = Client::query();
        $this->scopeVisibleClients($visible);
        $counts = [
            'all' => (clone $visible)->count(),
            'active' => (clone $visible)->where('status', 'Active')->count(),
            'inactive' => (clone $visible)->where('status', 'Inactive')->count(),
        ];
        $query = $visible->withCount(['projects', 'communications']);
        if (! empty($validated['q'])) {
            $term = '%'.trim($validated['q']).'%';
            $query->where(fn (Builder $query) => $query->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)->orWhere('mobile_number', 'like', $term)
                ->orWhere('website', 'like', $term));
        }
        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }
        $paginator = $query->orderBy('name')->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            'status' => true,
            'message' => $search ? 'Client search results retrieved successfully.' : 'Clients retrieved successfully.',
            'counts' => $counts,
            'total' => $paginator->total(),
            'data' => collect($paginator->items())->map(fn (Client $client) => $this->clientData($client)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function validateClient(Request $request, bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'email' => [$presence, 'nullable', 'email', 'max:255'],
            'mobile_number' => [$presence, 'nullable', 'string', 'regex:/^[0-9+() -]{7,30}$/'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'address' => [$presence, 'nullable', 'string', 'max:2000'],
            'status' => [$presence, Rule::in(['Active', 'Inactive'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
    }

    private function validateCommunication(Request $request, bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$presence, Rule::in(['Email', 'Call', 'Meeting', 'Note'])],
            'subject' => [$presence, 'string', 'max:255'],
            'communicated_at' => [$presence, 'date'],
            'recipient' => [$presence, 'string', 'max:255'],
            'description' => [$presence, 'string', 'max:10000'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);
    }

    private function clientData(Client $client, bool $details = false): array
    {
        if ($details) {
            $client->load(['lead', 'creator:id,name,email', 'projects.projectManager', 'communications.creator']);
        }
        $data = [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'mobile_number' => $client->mobile_number,
            'website' => $client->website,
            'address' => $client->address,
            'status' => $client->status,
            'notes' => $client->notes,
            'lead_id' => $client->lead_id,
            'projects_count' => $client->projects_count ?? ($client->relationLoaded('projects') ? $client->projects->count() : $client->projects()->count()),
            'communications_count' => $client->communications_count ?? ($client->relationLoaded('communications') ? $client->communications->count() : $client->communications()->count()),
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
        ];
        if ($details) {
            $data['lead'] = $client->lead;
            $data['created_by'] = $client->creator;
            $data['projects'] = $client->projects->map(fn (Project $project) => $this->projectData($project));
            $data['communications'] = $client->communications->map(fn (ClientCommunication $item) => $this->communicationData($item));
        }

        return $data;
    }

    private function projectData(Project $project): array
    {
        $project->loadMissing(['projectManager:id,employee_id,name,email,designation,avatar', 'members:id,employee_id,name,email,designation,avatar']);

        return [
            'id' => $project->id,
            'lead_id' => $project->lead_id,
            'client_id' => $project->client_id,
            'name' => $project->name,
            'description' => $project->description,
            'category' => $project->category,
            'priority' => $project->priority,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'working_days' => $project->working_days,
            'estimated_value' => $project->agreed_value,
            'status' => $project->status,
            'progress' => $project->progress,
            'project_manager' => $project->projectManager,
            'team' => $project->members,
        ];
    }

    private function communicationData(ClientCommunication $communication): array
    {
        $communication->loadMissing('creator:id,name,email');

        return [
            'id' => $communication->id,
            'client_id' => $communication->client_id,
            'type' => $communication->type,
            'subject' => $communication->subject,
            'communicated_at' => $communication->communicated_at?->toIso8601String(),
            'recipient' => $communication->recipient,
            'description' => $communication->description,
            'attachment_name' => $communication->attachment_name,
            'attachment_url' => $communication->attachment_path ? Storage::disk('public')->url($communication->attachment_path) : null,
            'created_by' => $communication->creator,
            'created_at' => $communication->created_at,
            'updated_at' => $communication->updated_at,
        ];
    }

    private function storeAttachment(Request $request): array
    {
        if (! $request->hasFile('attachment')) {
            return [];
        }
        $file = $request->file('attachment');

        return [
            'attachment_path' => $file->store('client-communications', 'public'),
            'attachment_name' => $file->getClientOriginalName(),
        ];
    }

    private function ensureCommunicationClient(Client $client, ClientCommunication $communication): void
    {
        abort_unless($communication->client_id === $client->id, 404, 'Client communication not found.');
    }

    private function scopeVisibleClients(Builder $query): void
    {
        $user = request()->user();
        if (strtolower((string) $user->role) === 'admin') {
            return;
        }
        $query->where(function (Builder $query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhereHas('lead', fn (Builder $lead) => $lead->where('assigned_to', $user->id))
                ->orWhereHas('projects', fn (Builder $project) => $project
                    ->where('project_manager_id', $user->id)
                    ->orWhereHas('members', fn (Builder $member) => $member->whereKey($user->id)));
        });
    }

    private function authorizeClient(Client $client, bool $manage = false): void
    {
        $user = request()->user();
        if (strtolower((string) $user->role) === 'admin') {
            return;
        }
        $allowed = $client->created_by === $user->id;
        if (! $manage) {
            $allowed = $allowed
                || $client->lead()->where('assigned_to', $user->id)->exists()
                || $client->projects()->where(function (Builder $query) use ($user) {
                    $query->where('project_manager_id', $user->id)
                        ->orWhereHas('members', fn (Builder $member) => $member->whereKey($user->id));
                })->exists();
        }
        abort_unless($allowed, 404, 'Client not found.');
    }

    private function authorizeCommunicationMutation(ClientCommunication $communication): void
    {
        $user = request()->user();
        abort_unless(
            strtolower((string) $user->role) === 'admin' || $communication->created_by === $user->id,
            403,
            'You can only update or delete communications that you created.'
        );
    }
}
