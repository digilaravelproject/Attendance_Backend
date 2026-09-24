<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in(['all', 'read', 'unread'])],
            'module' => ['sometimes', 'string', 'max:80'],
            'type' => ['sometimes', 'string', 'max:80'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        return $this->listFor($request, $request->user());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $notification = $this->owned($request->user(), $id);
        if (! $notification) {
            return $this->notFound();
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification retrieved successfully.',
            'data' => $this->data($notification),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->owned($request->user(), $id);
        if (! $notification) {
            return $this->notFound();
        }
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read.',
            'data' => $this->data($notification->fresh('actor:id,name,avatar')),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read.',
            'updated' => $updated,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $this->owned($request->user(), $id);
        if (! $notification) {
            return $this->notFound();
        }
        $notification->delete();

        return response()->json(['status' => true, 'message' => 'Notification deleted successfully.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $deleted = Notification::where('recipient_id', $request->user()->id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'All notifications deleted successfully.',
            'deleted' => $deleted,
        ]);
    }

    public function employeeIndex(Request $request, string $employeeId): JsonResponse
    {
        $employee = $this->employee($employeeId);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        return $this->listFor($request, $employee);
    }

    public function employeeShow(Request $request, string $employeeId, string $id): JsonResponse
    {
        $employee = $this->employee($employeeId);
        $notification = $employee ? $this->owned($employee, $id) : null;
        if (! $notification) {
            return $this->notFound();
        }

        return response()->json([
            'status' => true,
            'message' => 'Employee notification retrieved successfully.',
            'data' => $this->data($notification),
        ]);
    }

    public function employeeMarkRead(Request $request, string $employeeId, string $id): JsonResponse
    {
        $employee = $this->employee($employeeId);
        $notification = $employee ? $this->owned($employee, $id) : null;
        if (! $notification) {
            return $this->notFound();
        }
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json([
            'status' => true,
            'message' => 'Employee notification marked as read.',
            'data' => $this->data($notification->fresh('actor:id,name,avatar')),
        ]);
    }

    public function employeeDestroy(Request $request, string $employeeId, string $id): JsonResponse
    {
        $employee = $this->employee($employeeId);
        $notification = $employee ? $this->owned($employee, $id) : null;
        if (! $notification) {
            return $this->notFound();
        }
        $notification->delete();

        return response()->json(['status' => true, 'message' => 'Employee notification deleted successfully.']);
    }

    private function listFor(Request $request, User $recipient): JsonResponse
    {
        $query = Notification::with('actor:id,name,avatar')->where('recipient_id', $recipient->id);
        $unread = Notification::where('recipient_id', $recipient->id)->whereNull('read_at')->count();
        if ($request->input('status') === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($request->input('status') === 'unread') {
            $query->whereNull('read_at');
        }
        foreach (['module', 'type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('per_page')) {
            $page = $query->latest()->paginate($request->integer('per_page'));

            return response()->json([
                'status' => true,
                'message' => 'Notifications retrieved successfully.',
                'total' => $page->total(),
                'unread_count' => $unread,
                'data' => collect($page->items())->map(fn (Notification $item) => $this->data($item)),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                ],
            ]);
        }

        $items = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Notifications retrieved successfully.',
            'total' => $items->count(),
            'unread_count' => $unread,
            'data' => $items->map(fn (Notification $item) => $this->data($item)),
        ]);
    }

    private function owned(User $recipient, string $id): ?Notification
    {
        return Notification::with('actor:id,name,avatar')
            ->where('recipient_id', $recipient->id)->find($id);
    }

    private function employee(string $id): ?User
    {
        return User::where('role', 'employee')->find($id);
    }

    private function data(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'module' => $notification->module,
            'action' => $notification->action,
            'entity_type' => $notification->entity_type,
            'entity_id' => $notification->entity_id,
            'data' => $notification->data,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'time_ago' => $notification->created_at?->diffForHumans(),
            'actor' => $notification->actor,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Notification not found.'], 404);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
