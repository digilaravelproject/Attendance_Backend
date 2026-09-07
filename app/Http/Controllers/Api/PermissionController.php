<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Get permission list with action status for a role (Requirement 2 & Screenshot 1)
     */
    public function index(Request $request, ?string $role_id = null): JsonResponse
    {
        $roleId = $role_id ?? $request->input('role_id');

        $assignedPermissionIds = [];
        $role = null;

        if ($roleId) {
            $role = Role::find($roleId);
            if (!$role) {
                return response()->json([
                    'status' => false,
                    'message' => 'Role not found.',
                ], 404);
            }
            $assignedPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();
        }

        $allPermissions = Permission::orderBy('id')->get();

        // Group by module for matrix view (Screenshot 1 UI)
        $groupedModules = [];
        foreach ($allPermissions as $perm) {
            $slug = $perm->module_slug;
            if (!isset($groupedModules[$slug])) {
                $groupedModules[$slug] = [
                    'module' => $perm->module,
                    'module_slug' => $slug,
                    'description' => $perm->description,
                    'actions' => [],
                ];
            }

            $isAllowed = in_array($perm->id, $assignedPermissionIds);

            $groupedModules[$slug]['actions'][$perm->action] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'status' => $isAllowed ? 'allowed' : 'not_allowed',
                'allowed' => $isAllowed,
            ];
        }

        // Format flat permissions list
        $permissionsList = $allPermissions->map(function ($perm) use ($assignedPermissionIds) {
            $isAllowed = in_array($perm->id, $assignedPermissionIds);
            return [
                'id' => $perm->id,
                'module' => $perm->module,
                'module_slug' => $perm->module_slug,
                'action' => $perm->action,
                'name' => $perm->name,
                'description' => $perm->description,
                'is_assigned' => $isAllowed,
                'status' => $isAllowed ? 'allowed' : 'not_allowed',
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Permissions retrieved successfully.',
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ] : null,
            'total_permissions' => $allPermissions->count(),
            'assigned_permissions_count' => count($assignedPermissionIds),
            'modules' => array_values($groupedModules),
            'data' => $permissionsList,
        ], 200);
    }
}
