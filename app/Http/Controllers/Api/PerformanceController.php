<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerformanceMessage;
use App\Models\Task;
use App\Models\TaskQualityReview;
use App\Models\User;
use App\PerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceService $performance) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedPeriod($request);
        if ($filters instanceof JsonResponse) {
            return $filters;
        }

        [$start, $end] = $this->performance->period($filters);
        $query = $this->visibleEmployeesQuery($request->user())->with(['designationDetails', 'departmentDetails']);
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('designation', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%"));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('team')) {
            $query->where('team', $request->input('team'));
        }

        $ranked = $query->get()->map(fn (User $employee) => $this->performance->summary($employee, $start, $end))
            ->sort(fn (array $a, array $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1))->values()
            ->map(fn (array $item, int $index) => [...$item, 'rank' => $index + 1]);
        $items = $request->input('view') === 'department'
            ? $ranked->sort(function (array $a, array $b) {
                $department = strcasecmp((string) $a['employee']['department'], (string) $b['employee']['department']);

                return $department !== 0 ? $department : (($b['score'] ?? -1) <=> ($a['score'] ?? -1));
            })->values()
            : $ranked;

        $rated = $items->whereNotNull('score');
        $top = $rated->sortByDesc('score')->first();
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $page = max($request->integer('page', 1), 1);

        return response()->json([
            'status' => true,
            'message' => 'Employee performance retrieved successfully.',
            'summary' => [
                'total_employees' => $items->count(),
                'rated_employees' => $rated->count(),
                'average_performance' => $rated->isNotEmpty() ? round((float) $rated->avg('score'), 2) : null,
                'top_performer' => $top ? ['employee' => $top['employee'], 'score' => $top['score']] : null,
            ],
            'filters' => ['period' => $items->first()['period'] ?? ['from' => $start->toDateString(), 'to' => $end->toDateString()], 'view' => $request->input('view', 'team')],
            'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $items->count(), 'last_page' => max(1, (int) ceil($items->count() / $perPage))],
            'data' => $items->forPage($page, $perPage)->values(),
        ]);
    }

    public function show(Request $request, User $employee): JsonResponse
    {
        if ($response = $this->authorizeEmployee($request->user(), $employee)) {
            return $response;
        }
        $period = $this->resolvePeriod($request);
        if ($period instanceof JsonResponse) {
            return $period;
        }
        [$start, $end] = $period;

        $summary = $this->performance->summary($this->loadEmployee($employee), $start, $end);
        $summary['detailed_metrics'] = [
            'attendance' => $this->performance->attendance($employee, $start, $end)['summary'],
            'leave' => $this->performance->leave($employee, $start, $end)['summary'],
            'task_completion' => $summary['metrics']['task_completion'],
            'timely_submission' => $summary['metrics']['timely_submission'],
            'quality_of_work' => $summary['metrics']['quality_of_work'],
        ];
        $scores = $this->visibleEmployeesQuery($request->user())->with(['designationDetails', 'departmentDetails'])->get()
            ->map(fn (User $visible) => ['id' => $visible->id, 'score' => $this->performance->summary($visible, $start, $end)['score']])
            ->sortByDesc(fn (array $item) => $item['score'] ?? -1)->values();
        $rank = $scores->search(fn (array $item) => $item['id'] === $employee->id);

        return response()->json(['status' => true, 'message' => 'Employee performance retrieved successfully.', 'data' => [...$summary, 'rank' => $rank === false ? null : $rank + 1]]);
    }

    public function attendance(Request $request, User $employee): JsonResponse
    {
        return $this->detailResponse($request, $employee, 'attendance');
    }

    public function leave(Request $request, User $employee): JsonResponse
    {
        return $this->detailResponse($request, $employee, 'leave');
    }

    public function tasks(Request $request, User $employee): JsonResponse
    {
        return $this->detailResponse($request, $employee, 'tasks');
    }

    public function quality(Request $request, User $employee): JsonResponse
    {
        return $this->detailResponse($request, $employee, 'quality');
    }

    public function storeQualityReview(Request $request, User $employee): JsonResponse
    {
        if ($response = $this->authorizeManagerTarget($request->user(), $employee)) {
            return $response;
        }
        $validator = Validator::make($request->all(), [
            'task_id' => ['required', 'integer', Rule::exists('tasks', 'id')],
            'deliverable_accuracy' => 'required|integer|between:0,100',
            'deadline_adherence' => 'required|integer|between:0,100',
            'defect_prevention' => 'required|integer|between:0,100',
            'collaboration' => 'required|integer|between:0,100',
            'feedback' => 'nullable|string|max:5000',
            'reviewed_at' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $task = Task::query()->whereKey($request->integer('task_id'))
            ->whereHas('assignees', fn (Builder $query) => $query->whereKey($employee->id))->first();
        if (! $task) {
            return response()->json(['status' => false, 'message' => 'The selected task is not assigned to this employee.'], 422);
        }

        $data = $validator->validated();
        $review = TaskQualityReview::updateOrCreate(
            ['task_id' => $task->id, 'employee_id' => $employee->id, 'reviewer_id' => $request->user()->id],
            [...$data, 'reviewed_at' => $data['reviewed_at'] ?? now()]
        );

        return response()->json(['status' => true, 'message' => 'Task quality review saved successfully.', 'data' => $review->load(['task:id,task_name,project_id', 'reviewer:id,name,designation'])], $review->wasRecentlyCreated ? 201 : 200);
    }

    public function messages(Request $request, User $employee): JsonResponse
    {
        if ($response = $this->authorizeEmployee($request->user(), $employee)) {
            return $response;
        }
        $actor = $request->user();
        $query = PerformanceMessage::with(['sender:id,name,avatar,designation', 'receiver:id,name,avatar,designation']);
        if ($actor->id === $employee->id) {
            $query->where(fn (Builder $builder) => $builder->where('sender_id', $actor->id)->orWhere('receiver_id', $actor->id));
        } else {
            $query->where(fn (Builder $builder) => $builder->where('sender_id', $actor->id)->where('receiver_id', $employee->id))
                ->orWhere(fn (Builder $builder) => $builder->where('sender_id', $employee->id)->where('receiver_id', $actor->id));
        }

        $messages = $query->orderBy('created_at')->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return response()->json(['status' => true, 'message' => 'Performance message history retrieved successfully.', 'data' => $messages->items(), 'pagination' => ['current_page' => $messages->currentPage(), 'per_page' => $messages->perPage(), 'total' => $messages->total(), 'last_page' => $messages->lastPage()]]);
    }

    public function sendMessage(Request $request, User $employee): JsonResponse
    {
        if ($response = $this->authorizeManagerTarget($request->user(), $employee)) {
            return $response;
        }
        $validator = Validator::make($request->all(), ['message' => 'required|string|max:5000']);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $message = PerformanceMessage::create(['sender_id' => $request->user()->id, 'receiver_id' => $employee->id, 'message' => trim($validator->validated()['message'])]);

        return response()->json(['status' => true, 'message' => 'Performance message sent successfully.', 'data' => $message->load(['sender:id,name,avatar,designation', 'receiver:id,name,avatar,designation'])], 201);
    }

    private function detailResponse(Request $request, User $employee, string $method): JsonResponse
    {
        if ($response = $this->authorizeEmployee($request->user(), $employee)) {
            return $response;
        }
        $period = $this->resolvePeriod($request);
        if ($period instanceof JsonResponse) {
            return $period;
        }
        [$start, $end] = $period;
        $data = $this->performance->{$method}($this->loadEmployee($employee), $start, $end);

        return response()->json(['status' => true, 'message' => ucfirst($method).' details retrieved successfully.', 'data' => $data]);
    }

    private function resolvePeriod(Request $request): array|JsonResponse
    {
        $filters = $this->validatedPeriod($request);

        return $filters instanceof JsonResponse ? $filters : $this->performance->period($filters);
    }

    private function validatedPeriod(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => 'nullable|date_format:Y-m',
            'from' => 'nullable|required_with:to|date_format:Y-m-d',
            'to' => 'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from',
            'view' => 'nullable|in:team,department',
            'department_id' => 'nullable|integer|exists:departments,id',
            'team' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $validator->fails() ? $this->validationError($validator->errors()->toArray()) : $validator->validated();
    }

    private function visibleEmployeesQuery(User $actor): Builder
    {
        $query = User::query()->where('role', 'employee');
        if ($this->isAdmin($actor)) {
            return $query;
        }
        if ($this->isManager($actor)) {
            return $query->where(fn (Builder $builder) => $builder->whereKey($actor->id)->orWhere('reporting_manager_id', $actor->id));
        }

        return $query->whereKey($actor->id);
    }

    private function authorizeEmployee(User $actor, User $employee): ?JsonResponse
    {
        if ($employee->role !== 'employee' || ! $this->visibleEmployeesQuery($actor)->whereKey($employee->id)->exists()) {
            return response()->json(['status' => false, 'message' => 'You are not allowed to view this employee performance.'], 403);
        }

        return null;
    }

    private function authorizeManagerTarget(User $actor, User $employee): ?JsonResponse
    {
        if (! $this->isAdmin($actor) && ! $this->isManager($actor)) {
            return response()->json(['status' => false, 'message' => 'An administrator or manager is required.'], 403);
        }
        if ($actor->id === $employee->id || ($response = $this->authorizeEmployee($actor, $employee))) {
            return $response ?? response()->json(['status' => false, 'message' => 'Select an employee other than yourself.'], 422);
        }

        return null;
    }

    private function isAdmin(User $user): bool
    {
        return strtolower((string) $user->role) === 'admin';
    }

    private function isManager(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return false;
        }

        return $user->designationDetails?->hierarchy_level === 'manager'
            || $user->roles()->where('name', 'like', '%manager%')->exists();
    }

    private function loadEmployee(User $employee): User
    {
        return $employee->loadMissing(['designationDetails', 'departmentDetails']);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
