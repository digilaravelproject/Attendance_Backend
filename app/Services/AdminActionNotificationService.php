<?php

namespace App\Services;

use App\Models\AssignedShift;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminActionNotificationService
{
    /**
     * Capture employee recipients before a destructive/update request is executed.
     *
     * @return array<int>
     */
    public function recipientsBefore(Request $request): array
    {
        $segments = $request->segments();
        $resource = $segments[2] ?? null;
        $id = isset($segments[3]) && ctype_digit((string) $segments[3]) ? (int) $segments[3] : null;
        $ids = collect();

        if ($resource === 'employees' && $id && $request->method() !== 'DELETE') {
            $ids->push($id);
        }
        if ($resource === 'leave-requests' && $id) {
            $ids->push(LeaveRequest::whereKey($id)->value('user_id'));
        }
        if ($resource === 'assigned-shifts' && $id) {
            $ids->push(AssignedShift::whereKey($id)->value('user_id'));
        }
        if (in_array($resource, ['departments', 'designations'], true)
            && ($segments[4] ?? null) === 'employees'
            && isset($segments[5]) && ctype_digit((string) $segments[5])) {
            $ids->push((int) $segments[5]);
        }
        if ($resource === 'shift-rotations'
            && ($segments[4] ?? null) === 'users'
            && isset($segments[5]) && ctype_digit((string) $segments[5])) {
            $ids->push((int) $segments[5]);
        }

        foreach (['employee_ids', 'user_ids'] as $key) {
            $ids = $ids->merge((array) $request->input($key, []));
        }
        if ($request->filled('user_id')) {
            $ids->push($request->integer('user_id'));
        }

        return User::where('role', 'employee')
            ->whereIn('id', $ids->filter()->map(fn ($value) => (int) $value)->unique())
            ->pluck('id')->all();
    }

    public function record(Request $request, Response $response, array $employeeIds = []): void
    {
        $admin = $request->user();
        if (! $admin instanceof User || strtolower((string) $admin->role) !== 'admin') {
            return;
        }

        $path = trim($request->path(), '/');
        if (str_contains($path, '/notifications') || str_contains($path, '/employee-notifications')) {
            return;
        }

        $payload = $this->responsePayload($response);
        $meta = $this->metadata($request, $payload);

        Notification::create([
            'recipient_id' => $admin->id,
            'actor_id' => $admin->id,
            'type' => 'admin_action',
            'title' => $meta['admin_title'],
            'message' => $payload['message'] ?? $meta['admin_message'],
            'module' => $meta['module'],
            'action' => $meta['action'],
            'entity_type' => $meta['entity_type'],
            'entity_id' => $meta['entity_id'],
            'data' => ['method' => $request->method(), 'path' => '/'.$path],
        ]);

        if ($meta['module'] === 'employees' && $meta['is_create']) {
            $createdId = data_get($payload, 'data.id');
            if ($createdId) {
                $employeeIds[] = (int) $createdId;
                $meta['entity_id'] = (int) $createdId;
            }
        }

        foreach (array_unique(array_map('intval', $employeeIds)) as $employeeId) {
            if (! User::where('role', 'employee')->whereKey($employeeId)->exists()) {
                continue;
            }
            Notification::create([
                'recipient_id' => $employeeId,
                'actor_id' => $admin->id,
                'type' => 'employee_action',
                'title' => $meta['employee_title'],
                'message' => $this->employeeMessage($meta),
                'module' => $meta['module'],
                'action' => $meta['action'],
                'entity_type' => $meta['entity_type'],
                'entity_id' => $meta['entity_id'],
                'data' => ['performed_by' => $admin->name],
            ]);
        }
    }

    private function responsePayload(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function metadata(Request $request, array $payload): array
    {
        $segments = $request->segments();
        $module = (string) ($segments[2] ?? 'administration');
        $pathEntityId = isset($segments[3]) && ctype_digit((string) $segments[3])
            ? (int) $segments[3]
            : null;
        $entityId = $pathEntityId ?? (int) (data_get($payload, 'data.id') ?: 0);
        $last = strtolower((string) end($segments));
        $action = match (true) {
            in_array($last, ['approve', 'reject'], true) => $last,
            $request->method() === 'DELETE' => 'deleted',
            in_array($request->method(), ['PUT', 'PATCH'], true) => 'updated',
            $request->method() === 'POST' && $pathEntityId !== null => 'updated',
            default => 'created',
        };
        $labels = [
            'employees' => 'Employee', 'departments' => 'Department', 'designations' => 'Designation',
            'roles' => 'Role', 'shifts' => 'Shift', 'assigned-shifts' => 'Shift Assignment',
            'shift-rotations' => 'Shift Rotation', 'leave-requests' => 'Leave Request',
            'leave-types' => 'Leave Type', 'holidays' => 'Holiday', 'profile' => 'Profile',
            'update-profile' => 'Profile', 'update-password' => 'Password',
        ];
        $label = $labels[$module] ?? str($module)->replace('-', ' ')->title()->toString();
        $pastAction = match ($action) {
            'approve' => 'Approved', 'reject' => 'Rejected', 'created' => 'Created',
            'updated' => 'Updated', 'deleted' => 'Deleted', default => ucfirst($action),
        };

        return [
            'module' => $module,
            'action' => $action,
            'entity_type' => $module,
            'entity_id' => $entityId ?: null,
            'is_create' => $action === 'created',
            'admin_title' => "{$label} {$pastAction}",
            'admin_message' => "{$label} was successfully ".strtolower($pastAction).'.',
            'employee_title' => match (true) {
                $module === 'leave-requests' && $action === 'approve' => 'Leave Request Approved',
                $module === 'leave-requests' && $action === 'reject' => 'Leave Request Rejected',
                in_array($module, ['assigned-shifts', 'shift-rotations', 'shifts'], true) => 'New Shift Schedule',
                in_array($module, ['departments', 'designations'], true) => 'Employee Assignment Updated',
                $module === 'employees' && $action === 'created' => 'Employee Account Created',
                $module === 'employees' => 'Employee Profile Updated',
                default => "{$label} {$pastAction}",
            },
        ];
    }

    private function employeeMessage(array $meta): string
    {
        return match (true) {
            $meta['module'] === 'leave-requests' && $meta['action'] === 'approve' => 'Your leave request has been approved by the administrator.',
            $meta['module'] === 'leave-requests' && $meta['action'] === 'reject' => 'Your leave request has been rejected by the administrator.',
            in_array($meta['module'], ['assigned-shifts', 'shift-rotations', 'shifts'], true) => 'Your shift schedule has been updated by the administrator.',
            in_array($meta['module'], ['departments', 'designations'], true) => 'Your department or designation assignment has been updated by the administrator.',
            $meta['module'] === 'employees' && $meta['action'] === 'created' => 'Your employee account has been created by the administrator.',
            $meta['module'] === 'employees' => 'Your employee profile has been updated by the administrator.',
            default => 'An administrator updated information related to your account.',
        };
    }
}
