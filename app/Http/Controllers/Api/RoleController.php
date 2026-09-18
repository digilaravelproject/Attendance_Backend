<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->with('permissions')->withCount('permissions');
        $search = trim((string) $request->input('search', $request->input('query', '')));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $query->latest()->get()->map(fn (Role $role) => $this->roleData($role));

        return response()->json([
            'status' => true,
            'message' => 'Role listing retrieved successfully.',
            'total' => $roles->count(),
            'total_permissions' => Permission::count(),
            'data' => $roles,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if (! $request->filled('query') && ! $request->filled('search')) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide a search term using query parameter ?query= or ?search=',
            ], 422);
        }

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('role_name') && ! $request->has('name')) {
            $request->merge(['name' => $request->input('role_name')]);
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $role = Role::create([
            'name' => trim($request->input('name')),
            'department' => trim($request->input('department')),
            'description' => $request->input('description'),
            'status' => $request->boolean('status', true),
        ]);

        $role->permissions()->sync($request->input('permission_ids', []));

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully.',
            'data' => $this->freshRole($role),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->withCount('permissions')->find($id);
        if (! $role) {
            return response()->json(['status' => false, 'message' => 'Role not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role details retrieved successfully.',
            'data' => $this->roleData($role),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        if (! $role) {
            return response()->json(['status' => false, 'message' => 'Role not found.'], 404);
        }

        if ($request->has('role_name') && ! $request->has('name')) {
            $request->merge(['name' => $request->input('role_name')]);
        }

        $validator = Validator::make($request->all(), $this->rules($role));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        foreach (['name', 'department', 'description'] as $field) {
            if ($request->has($field)) {
                $role->{$field} = is_string($request->input($field))
                    ? trim($request->input($field))
                    : $request->input($field);
            }
        }
        if ($request->has('status')) {
            $role->status = $request->boolean('status');
        }
        $role->save();

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->input('permission_ids', []));
        }

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully.',
            'data' => $this->freshRole($role),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $role = Role::find($id);
        if (! $role) {
            return response()->json(['status' => false, 'message' => 'Role not found.'], 404);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function assignPermissions(Request $request, ?string $id = null): JsonResponse
    {
        $roleId = $id ?? $request->input('role_id');
        $role = $roleId ? Role::find($roleId) : null;

        if (! $role) {
            return response()->json([
                'status' => false,
                'message' => $roleId ? 'Role not found.' : 'Please provide role_id.',
            ], $roleId ? 404 : 422);
        }

        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|distinct|exists:permissions,id',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $role->permissions()->sync($request->input('permission_ids'));

        return response()->json([
            'status' => true,
            'message' => "Permissions assigned to role '{$role->name}' successfully.",
            'data' => $this->freshRole($role),
        ]);
    }

    private function rules(?Role $role = null): array
    {
        $presence = $role ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255', 'unique:roles,name,' . ($role?->id ?? 'NULL')],
            'department' => [$presence, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    private function freshRole(Role $role): array
    {
        $fresh = Role::with('permissions')->withCount('permissions')->findOrFail($role->id);

        return $this->roleData($fresh);
    }

    private function roleData(Role $role): array
    {
        $data = $role->toArray();
        $total = Permission::count();
        $data['permission_ids'] = $role->permissions->pluck('id')->values();
        $data['granted_permissions'] = $role->permissions_count;
        $data['total_permissions'] = $total;
        $data['permissions_progress'] = "{$role->permissions_count} / {$total} Granted";

        return $data;
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation error',
            'errors' => $errors,
        ], 422);
    }
}
