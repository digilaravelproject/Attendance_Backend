<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function total(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Total permission count retrieved successfully.',
            'total_permissions' => Permission::count(),
            'total_categories' => Permission::distinct()->count('module_slug'),
        ]);
    }

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
                    'permissions' => [],
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
            $groupedModules[$slug]['permissions'][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'slug' => $perm->action,
                'description' => $perm->description,
                'is_assigned' => $isAllowed,
                'status' => $isAllowed ? 'allowed' : 'not_allowed',
            ];
        }

        $groupedModules = array_map(function (array $module) {
            $module['total_permissions'] = count($module['permissions']);
            $module['assigned_permissions_count'] = count(array_filter(
                $module['permissions'],
                fn (array $permission) => $permission['is_assigned']
            ));
            $module['all_assigned'] = $module['total_permissions'] > 0
                && $module['assigned_permissions_count'] === $module['total_permissions'];

            return $module;
        }, $groupedModules);

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
                'department' => $role->department,
                'status' => $role->status,
            ] : null,
            'total_permissions' => $allPermissions->count(),
            'total_categories' => count($groupedModules),
            'assigned_permissions_count' => count($assignedPermissionIds),
            'modules' => array_values($groupedModules),
            'data' => $permissionsList,
        ], 200);
    }
}
