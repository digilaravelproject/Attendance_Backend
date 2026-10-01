<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskSubtask;
use App\Models\TaskTimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * Resolve user from Sanctum token, header X-User-Id, or request input user_id.
     */
    protected function resolveUser(Request $request)
    {
        $user = auth('sanctum')->user() ?? $request->user();

        if (! $user) {
            $userId = $request->header('X-User-Id') ?? $request->input('user_id');
            if ($userId) {
                $user = User::find($userId);
            }
        }

        if (! $user) {
            // Default to first user if available for demo/unauthenticated testing
            $user = User::first();
        }

        return $user;
    }

    /**
     * 1. Create Task with assigning multiple employees (Screenshots 1 & 2)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'category' => 'nullable|string|max:255',
            'priority' => 'nullable|in:Low,Medium,High,Urgent',
            'status' => 'nullable|in:Pending,In Progress,On Hold,Completed,In Review,Submitted For Testing,Cancelled',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|string|max:100',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:users,id',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240', // 10MB per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $this->resolveUser($request);

        $task = Task::create([
            'task_name' => $request->input('task_name'),
            'description' => $request->input('description'),
            'project_id' => $request->input('project_id'),
            'category' => $request->input('category', 'General'),
            'priority' => $request->input('priority', 'Medium'),
            'status' => $request->input('status', 'Pending'),
            'start_date' => $request->input('start_date'),
            'due_date' => $request->input('due_date'),
            'estimated_hours' => $request->input('estimated_hours'),
            'created_by' => $user ? $user->id : 1,
        ]);

        // Assign multiple employees
        if ($request->has('employee_ids') && is_array($request->input('employee_ids'))) {
            $pivotData = [];
            foreach ($request->input('employee_ids') as $empId) {
                $pivotData[$empId] = ['assigned_by' => $user ? $user->id : null];
            }
            $task->assignees()->sync($pivotData);
        }

        // Handle uploaded files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('task_attachments', 'public');
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientMimeType(),
                    'uploaded_by' => $user ? $user->id : 1,
                ]);
            }
        }

        $task->load(['project', 'creator:id,name,email,avatar,role', 'assignees:id,name,email,avatar,role,designation', 'attachments']);

        return response()->json([
            'status' => true,
            'message' => 'Task created successfully',
            'data' => $task,
        ], 201);
    }

    /**
     * 2. Get all employees list (Employee as well as manager)
     */
    public function getEmployees(Request $request)
    {
        $query = User::query();

        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        // Fetch all active users with roles
        $employees = $query->select('id', 'name', 'email', 'phone', 'role', 'designation', 'designation_id', 'department', 'department_id', 'avatar', 'status')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Employees fetched successfully',
            'data' => $employees,
        ]);
    }

    /**
     * 3. Get total task & cards summary (Screenshots 3 & 4)
     * Admin logged in -> created tasks
     * Manager logged in -> created as well as assigned tasks
     * Employee logged in -> assigned tasks
     */
    public function total(Request $request)
    {
        $user = $this->resolveUser($request);

        $query = Task::query();

        if ($user) {
            $role = strtolower($user->role ?? 'employee');
            $isManager = $role === 'manager' || str_contains(strtolower($user->designation ?? ''), 'manager');

            if ($request->has('filter_scope')) {
                $scope = $request->input('filter_scope');
                if ($scope === 'created') {
                    $query->where('created_by', $user->id);
                } elseif ($scope === 'assigned') {
                    $query->whereHas('assignees', function ($q) use ($user) {
                        $q->where('users.id', $user->id);
                    });
                }
            } else {
                if ($role === 'admin') {
                    $query->where('created_by', $user->id);
                } elseif ($isManager) {
                    $query->where(function ($q) use ($user) {
                        $q->where('created_by', $user->id)
                          ->orWhereHas('assignees', function ($aQ) use ($user) {
                              $aQ->where('users.id', $user->id);
                          });
                    });
                } else {
                    $query->whereHas('assignees', function ($aQ) use ($user) {
                        $aQ->where('users.id', $user->id);
                    });
                }
            }
        }

        $tasks = $query->get();

        $stats = [
            'total_tasks' => $tasks->count(),
            'pending' => $tasks->where('status', 'Pending')->count(),
            'in_progress' => $tasks->where('status', 'In Progress')->count(),
            'on_hold' => $tasks->where('status', 'On Hold')->count(),
            'completed' => $tasks->where('status', 'Completed')->count(),
            'in_review' => $tasks->where('status', 'In Review')->count(),
            'submitted_for_testing' => $tasks->where('status', 'Submitted For Testing')->count(),
            'cancelled' => $tasks->where('status', 'Cancelled')->count(),
            'urgent_tasks' => $tasks->where('priority', 'Urgent')->count(),
            'high_priority' => $tasks->where('priority', 'High')->count(),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Task summary statistics retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Get tasks list with filters (Screenshots 3 & 4)
     */
    public function index(Request $request)
    {
        $user = $this->resolveUser($request);

        $query = Task::with([
            'project:id,name',
            'creator:id,name,email,avatar,role',
            'assignees:id,name,email,avatar,role,designation',
            'subtasks',
        ]);

        if ($user) {
            $role = strtolower($user->role ?? 'employee');
            $isManager = $role === 'manager' || str_contains(strtolower($user->designation ?? ''), 'manager');

            if ($request->has('filter_scope')) {
                $scope = $request->input('filter_scope');
                if ($scope === 'created') {
                    $query->where('created_by', $user->id);
                } elseif ($scope === 'assigned') {
                    $query->whereHas('assignees', function ($q) use ($user) {
                        $q->where('users.id', $user->id);
                    });
                }
            } else {
                if ($role === 'admin') {
                    $query->where('created_by', $user->id);
                } elseif ($isManager) {
                    $query->where(function ($q) use ($user) {
                        $q->where('created_by', $user->id)
                          ->orWhereHas('assignees', function ($aQ) use ($user) {
                              $aQ->where('users.id', $user->id);
                          });
                    });
                } else {
                    $query->whereHas('assignees', function ($aQ) use ($user) {
                        $aQ->where('users.id', $user->id);
                    });
                }
            }
        }

        // Apply filters
        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('priority') && $request->input('priority') !== 'all') {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('task_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $tasks = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Tasks fetched successfully',
            'data' => $tasks,
        ]);
    }

    /**
     * 4. Start / Pause / Stop task timer (Screenshots 12 & 13)
     */
    public function timerAction(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:start,pause,stop',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $action = $request->input('action');
        $user = $this->resolveUser($request);
        $userId = $user ? $user->id : 1;
        $now = Carbon::now();

        if ($action === 'start') {
            if (! $task->is_timer_running) {
                $task->is_timer_running = true;
                $task->timer_started_at = $now;

                if ($task->status === 'Pending') {
                    $task->status = 'In Progress';
                }

                $task->save();

                TaskTimeLog::create([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'action' => 'start',
                    'started_at' => $now,
                    'note' => $request->input('note', 'Started task timer'),
                ]);
            }
        } elseif ($action === 'pause') {
            if ($task->is_timer_running && $task->timer_started_at) {
                $elapsed = $now->diffInSeconds($task->timer_started_at);
                $task->total_logged_seconds += $elapsed;
                $task->is_timer_running = false;

                $startAt = $task->timer_started_at;
                $task->timer_started_at = null;
                $task->save();

                TaskTimeLog::create([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'action' => 'pause',
                    'started_at' => $startAt,
                    'stopped_at' => $now,
                    'duration_seconds' => $elapsed,
                    'note' => $request->input('note', 'Paused task timer'),
                ]);
            }
        } elseif ($action === 'stop') {
            if ($task->is_timer_running && $task->timer_started_at) {
                $elapsed = $now->diffInSeconds($task->timer_started_at);
                $task->total_logged_seconds += $elapsed;
                $task->is_timer_running = false;

                $startAt = $task->timer_started_at;
                $task->timer_started_at = null;
                $task->save();

                TaskTimeLog::create([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'action' => 'stop',
                    'started_at' => $startAt,
                    'stopped_at' => $now,
                    'duration_seconds' => $elapsed,
                    'note' => $request->input('note', 'Stopped task timer'),
                ]);
            } else {
                $task->is_timer_running = false;
                $task->timer_started_at = null;
                $task->save();
            }
        }

        $task->load(['project', 'creator:id,name,email,avatar,role', 'assignees:id,name,email,avatar,role,designation']);

        return response()->json([
            'status' => true,
            'message' => "Task timer {$action}ed successfully",
            'data' => $task,
        ]);
    }

    /**
     * Dedicated start endpoint
     */
    public function startTimer(Request $request, $id)
    {
        $request->merge(['action' => 'start']);
        return $this->timerAction($request, $id);
    }

    /**
     * Dedicated pause endpoint
     */
    public function pauseTimer(Request $request, $id)
    {
        $request->merge(['action' => 'pause']);
        return $this->timerAction($request, $id);
    }

    /**
     * Dedicated stop endpoint
     */
    public function stopTimer(Request $request, $id)
    {
        $request->merge(['action' => 'stop']);
        return $this->timerAction($request, $id);
    }

    /**
     * 5. Update Task Status (Screenshot 5)
     */
    public function updateStatus(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending,In Progress,On Hold,Completed,In Review,Submitted For Testing,Cancelled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->input('status');

        // If task is completed and timer is running, stop timer
        if ($newStatus === 'Completed' && $task->is_timer_running && $task->timer_started_at) {
            $now = Carbon::now();
            $elapsed = $now->diffInSeconds($task->timer_started_at);
            $task->total_logged_seconds += $elapsed;
            $task->is_timer_running = false;
            $task->timer_started_at = null;
        }

        $task->status = $newStatus;
        $task->save();

        $task->load(['project', 'creator:id,name,email,avatar,role', 'assignees:id,name,email,avatar,role,designation']);

        return response()->json([
            'status' => true,
            'message' => 'Task status updated successfully',
            'data' => $task,
        ]);
    }

    /**
     * 6. Get Task by ID (Screenshots 6, 7, 8, 9, 10)
     */
    public function show($id)
    {
        $task = Task::with([
            'project',
            'creator:id,name,email,avatar,role',
            'assignees:id,name,email,avatar,role,designation',
            'subtasks.assignedUser:id,name,email,avatar',
            'comments.user:id,name,email,avatar,role',
            'attachments.uploader:id,name,email',
            'timeLogs.user:id,name,email',
            'testingSubmittedBy:id,name,email',
        ])->find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Task details retrieved successfully',
            'data' => $task,
        ]);
    }

    /**
     * 7. Add Subtask in Task (Screenshot 11)
     */
    public function addSubtask(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $subtask = TaskSubtask::create([
            'task_id' => $task->id,
            'title' => $request->input('title'),
            'assigned_to' => $request->input('assigned_to'),
            'due_date' => $request->input('due_date'),
            'is_completed' => false,
        ]);

        $subtask->load('assignedUser:id,name,email,avatar');

        return response()->json([
            'status' => true,
            'message' => 'Subtask added successfully',
            'data' => $subtask,
        ], 201);
    }

    /**
     * Toggle Subtask completion
     */
    public function toggleSubtask(Request $request, $id, $subtaskId)
    {
        $subtask = TaskSubtask::where('task_id', $id)->where('id', $subtaskId)->first();

        if (! $subtask) {
            return response()->json([
                'status' => false,
                'message' => 'Subtask not found',
            ], 404);
        }

        $subtask->is_completed = ! $subtask->is_completed;
        $subtask->completed_at = $subtask->is_completed ? Carbon::now() : null;
        $subtask->save();

        return response()->json([
            'status' => true,
            'message' => 'Subtask status toggled successfully',
            'data' => $subtask,
        ]);
    }

    /**
     * Delete Subtask
     */
    public function deleteSubtask($id, $subtaskId)
    {
        $subtask = TaskSubtask::where('task_id', $id)->where('id', $subtaskId)->first();

        if (! $subtask) {
            return response()->json([
                'status' => false,
                'message' => 'Subtask not found',
            ], 404);
        }

        $subtask->delete();

        return response()->json([
            'status' => true,
            'message' => 'Subtask deleted successfully',
        ]);
    }

    /**
     * 8. Update Task
     */
    public function update(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'task_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'category' => 'nullable|string|max:255',
            'priority' => 'nullable|in:Low,Medium,High,Urgent',
            'status' => 'nullable|in:Pending,In Progress,On Hold,Completed,In Review,Submitted For Testing,Cancelled',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|string|max:100',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:users,id',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $this->resolveUser($request);

        $task->fill($request->only([
            'task_name',
            'description',
            'project_id',
            'category',
            'priority',
            'status',
            'start_date',
            'due_date',
            'estimated_hours',
        ]));

        $task->save();

        // Update assigned employees if provided
        if ($request->has('employee_ids')) {
            $employeeIds = $request->input('employee_ids', []);
            $pivotData = [];
            foreach ($employeeIds as $empId) {
                $pivotData[$empId] = ['assigned_by' => $user ? $user->id : null];
            }
            $task->assignees()->sync($pivotData);
        }

        // Handle uploaded new files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('task_attachments', 'public');
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getClientMimeType(),
                    'uploaded_by' => $user ? $user->id : 1,
                ]);
            }
        }

        $task->load(['project', 'creator:id,name,email,avatar,role', 'assignees:id,name,email,avatar,role,designation', 'attachments', 'subtasks']);

        return response()->json([
            'status' => true,
            'message' => 'Task updated successfully',
            'data' => $task,
        ]);
    }

    /**
     * 9. Delete Task
     */
    public function destroy($id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $task->delete();

        return response()->json([
            'status' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * Employee / Manager specific API 2: Submit for Testing (Screenshot 14)
     */
    public function submitForTesting(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string',
            'file' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $this->resolveUser($request);
        $userId = $user ? $user->id : 1;

        // If timer is running, pause/stop it
        if ($task->is_timer_running && $task->timer_started_at) {
            $now = Carbon::now();
            $elapsed = $now->diffInSeconds($task->timer_started_at);
            $task->total_logged_seconds += $elapsed;
            $task->is_timer_running = false;
            $task->timer_started_at = null;
        }

        $task->status = 'Submitted For Testing';
        $task->testing_submitted_at = Carbon::now();
        $task->testing_remarks = $request->input('remarks');
        $task->testing_submitted_by = $userId;
        $task->save();

        // Optional file attachment with submission
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('task_attachments', 'public');
            TaskAttachment::create([
                'task_id' => $task->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientMimeType(),
                'uploaded_by' => $userId,
            ]);
        }

        $task->load(['project', 'creator:id,name,email,avatar,role', 'assignees:id,name,email,avatar,role,designation', 'attachments', 'testingSubmittedBy:id,name,email']);

        return response()->json([
            'status' => true,
            'message' => 'Task submitted for testing successfully',
            'data' => $task,
        ]);
    }

    /**
     * Employee / Manager specific API 3: Add comment on task (Screenshot 15)
     */
    public function addComment(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
            'file' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $this->resolveUser($request);
        $userId = $user ? $user->id : 1;

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attachmentPath = $file->store('task_comments', 'public');
            $attachmentName = $file->getClientOriginalName();
        }

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'comment' => $request->input('comment'),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $comment->load('user:id,name,email,avatar,role');

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully',
            'data' => $comment,
        ], 201);
    }

    /**
     * Get comments for a task (Screenshot 15)
     */
    public function getComments($id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $comments = TaskComment::with('user:id,name,email,avatar,role')
            ->where('task_id', $id)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Comments retrieved successfully',
            'data' => $comments,
        ]);
    }
}
