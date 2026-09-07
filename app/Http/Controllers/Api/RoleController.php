<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Get all roles listing (supports search via ?search=query or ?query=search)
     * Requirement 5 & Screenshot 3: Includes total permission count and assigned user count.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with(['users:id,name,email,department,designation,avatar,status']);

        $searchTerm = $request->input('search', $request->input('query'));
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $roles = $query->withCount(['users', 'permissions'])->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Role listing retrieved successfully.',
            'total' => $roles->count(),
            'data' => $roles,
        ], 200);
    }

    /**
     * Search roles by keyword/query
     * Requirement 5 & Screenshot 3: Includes total permission count and assigned user count.
     */
    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->input('query', $request->input('search'));

        if (!$searchTerm) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide a search term using query parameter ?query= or ?search=',
            ], 422);
        }

        $roles = Role::with(['users:id,name,email,department,designation,avatar,status'])
            ->withCount(['users', 'permissions'])
            ->where('name', 'like', "%{$searchTerm}%")
            ->orWhere('description', 'like', "%{$searchTerm}%")
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Roles search results retrieved successfully.',
            'total' => $roles->count(),
            'data' => $roles,
        ], 200);
    }

    /**
     * Create a new Role
     */
    public function store(Request $request): JsonResponse
    {
        // Support both role_name and name
        $name = $request->input('role_name', $request->input('name'));
        $request->merge(['name' => $name]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
            'status' => 'nullable',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ], [
            'name.required' => 'The role name field is required.',
            'name.unique' => 'A role with this name already exists.',
            'user_ids.*.exists' => 'One or more selected user IDs do not exist.',
            'permission_ids.*.exists' => 'One or more selected permission IDs do not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = true;
        if ($request->has('status')) {
            $rawStatus = $request->input('status');
            $status = filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$rawStatus;
        }

        $role = Role::create([
            'name' => trim($name),
            'description' => $request->input('description'),
            'status' => $status,
        ]);

        // Sync assigned users if provided
        if ($request->has('user_ids') && is_array($request->input('user_ids'))) {
            $role->users()->sync($request->input('user_ids'));
        }

        // Sync permissions if provided
        if ($request->has('permission_ids') && is_array($request->input('permission_ids'))) {
            $role->permissions()->sync($request->input('permission_ids'));
        }

        $role->load(['users:id,name,email,department,designation,avatar,status', 'permissions']);
        $role->loadCount(['users', 'permissions']);

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    /**
     * Get role by ID with assigned users details & permissions count summary (Requirement 4 & Screenshot 2)
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::with([
            'users:id,name,email,mobile_number,phone,department,designation,employee_id,avatar,status',
            'permissions'
        ])
        ->withCount(['users', 'permissions'])
        ->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $assignedPermissions = $role->permissions;
        $totalPermissions = $role->permissions_count;

        $viewCount = $assignedPermissions->where('action', 'view')->pluck('module_slug')->unique()->count();
        $addCount = $assignedPermissions->where('action', 'add')->pluck('module_slug')->unique()->count();
        $editCount = $assignedPermissions->where('action', 'edit')->pluck('module_slug')->unique()->count();
        $deleteCount = $assignedPermissions->where('action', 'delete')->pluck('module_slug')->unique()->count();

        $permissionsSummary = [
            'total_permissions' => $totalPermissions,
            'view_permissions_count' => $viewCount,
            'add_permissions_count' => $addCount,
            'edit_permissions_count' => $editCount,
            'delete_permissions_count' => $deleteCount,
            'view_permissions' => [
                'count' => $viewCount,
                'label' => "{$viewCount} Modules",
            ],
            'add_permissions' => [
                'count' => $addCount,
                'label' => "{$addCount} Modules",
            ],
            'edit_permissions' => [
                'count' => $editCount,
                'label' => "{$editCount} Modules",
            ],
            'delete_permissions' => [
                'count' => $deleteCount,
                'label' => "{$deleteCount} Modules",
            ],
        ];

        // Format role response data
        $roleData = $role->toArray();
        $roleData['permissions_summary'] = $permissionsSummary;

        return response()->json([
            'status' => true,
            'message' => 'Role details retrieved successfully.',
            'data' => $roleData,
        ], 200);
    }

    /**
     * Update existing Role
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        // Support both role_name and name
        if ($request->has('role_name') && !$request->has('name')) {
            $request->merge(['name' => $request->input('role_name')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
            'status' => 'nullable',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ], [
            'name.unique' => 'A role with this name already exists.',
            'user_ids.*.exists' => 'One or more selected user IDs do not exist.',
            'permission_ids.*.exists' => 'One or more selected permission IDs do not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('name')) {
            $role->name = trim($request->input('name'));
        }

        if ($request->has('description')) {
            $role->description = $request->input('description');
        }

        if ($request->has('status')) {
            $rawStatus = $request->input('status');
            $role->status = filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$rawStatus;
        }

        $role->save();

        if ($request->has('user_ids') && is_array($request->input('user_ids'))) {
            $role->users()->sync($request->input('user_ids'));
        }

        if ($request->has('permission_ids') && is_array($request->input('permission_ids'))) {
            $role->permissions()->sync($request->input('permission_ids'));
        }

        $role->load(['users:id,name,email,department,designation,avatar,status', 'permissions']);
        $role->loadCount(['users', 'permissions']);

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully.',
            'data' => $role,
        ], 200);
    }

    /**
     * Delete Role
     */
    public function destroy(string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        // Detach assigned users and permissions before deleting
        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully.',
        ], 200);
    }

    /**
     * Remove user from role passing user_id (Requirement 1 & Screenshot 2 red minus button)
     */
    public function removeUser(Request $request, string $id, ?string $userId = null): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $targetUserId = $userId ?? $request->input('user_id');

        if (!$targetUserId) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide user_id to remove from the role.',
            ], 422);
        }

        $user = User::find($targetUserId);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Detach user from this role
        $role->users()->detach($targetUserId);

        $role->load(['users:id,name,email,department,designation,avatar,status']);
        $role->loadCount(['users', 'permissions']);

        return response()->json([
            'status' => true,
            'message' => "User '{$user->name}' removed from role '{$role->name}' successfully.",
            'data' => $role,
        ], 200);
    }

    /**
     * Assign permissions to particular role (Requirement 3 & Screenshot 1 Save button)
     */
    public function assignPermissions(Request $request, ?string $id = null): JsonResponse
    {
        $roleId = $id ?? $request->input('role_id');

        if (!$roleId) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide role_id.',
            ], 422);
        }

        $role = Role::find($roleId);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ], [
            'permission_ids.required' => 'Please provide an array of permission_ids to assign.',
            'permission_ids.*.exists' => 'One or more permission IDs are invalid or do not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $permissionIds = $request->input('permission_ids', []);

        // Sync permissions with the role
        $role->permissions()->sync($permissionIds);

        $role->load(['permissions']);
        $role->loadCount(['users', 'permissions']);

        return response()->json([
            'status' => true,
            'message' => "Permissions assigned to role '{$role->name}' successfully.",
            'assigned_permissions_count' => count($permissionIds),
            'data' => $role,
        ], 200);
    }
}
