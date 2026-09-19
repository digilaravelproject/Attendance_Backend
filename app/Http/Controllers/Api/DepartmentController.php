<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()->with('head')->withCount('employees');
        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('head', fn ($head) => $head->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $departments = $query->orderBy('name')->get()->map(fn (Department $item) => $this->data($item));

        return response()->json([
            'status' => true,
            'message' => 'Departments retrieved successfully.',
            'total' => $departments->count(),
            'data' => $departments,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required_without:search|string|max:255',
            'search' => 'required_without:query|string|max:255',
        ]);

        return $validator->fails()
            ? $this->validationError($validator->errors()->toArray())
            : $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $department = DB::transaction(function () use ($request) {
            $department = Department::create([
                'name' => trim($request->input('name')),
                'description' => $request->input('description'),
                'head_user_id' => $request->input('head_user_id'),
                'status' => $request->input('status', 'Active'),
            ]);
            $this->syncEmployees($department, $request->input('employee_ids', []), true);

            return $department;
        });

        return response()->json([
            'status' => true,
            'message' => 'Department created successfully.',
            'data' => $this->load($department),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['status' => false, 'message' => 'Department not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Department details retrieved successfully.',
            'data' => $this->load($department),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['status' => false, 'message' => 'Department not found.'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($department));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        DB::transaction(function () use ($request, $department) {
            $oldName = $department->name;
            $department->fill($request->only(['name', 'description', 'head_user_id', 'status']));
            if ($request->has('name')) {
                $department->name = trim($request->input('name'));
            }
            $department->save();

            if ($oldName !== $department->name) {
                $department->employees()->update(['department' => $department->name]);
            }
            if ($request->has('employee_ids')) {
                $this->syncEmployees($department, $request->input('employee_ids', []), false);
            } elseif ($department->head_user_id) {
                $this->assignEmployee($department, (int) $department->head_user_id);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Department updated successfully.',
            'data' => $this->load($department->fresh()),
        ]);
    }

    public function removeEmployee(string $id, string $employeeId): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['status' => false, 'message' => 'Department not found.'], 404);
        }
        $employee = User::where('role', 'employee')->where('department_id', $department->id)->find($employeeId);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee is not assigned to this department.'], 404);
        }
        if ((int) $department->head_user_id === (int) $employee->id) {
            return response()->json([
                'status' => false,
                'message' => 'The department head cannot be removed. Assign a different head first.',
            ], 422);
        }

        $employee->update(['department_id' => null, 'department' => null]);

        return response()->json([
            'status' => true,
            'message' => 'Employee removed from department successfully.',
            'data' => $this->load($department),
        ]);
    }

    public function addEmployees(Request $request, string $id): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['status' => false, 'message' => 'Department not found.'], 404);
        }
        $validator = Validator::make($request->all(), [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', $this->employeeExistsRule()],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        foreach ($request->input('employee_ids') as $employeeId) {
            $this->assignEmployee($department, (int) $employeeId);
        }

        return response()->json([
            'status' => true,
            'message' => 'Employees added to department successfully.',
            'data' => $this->load($department),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $department = Department::find($id);
        if (! $department) {
            return response()->json(['status' => false, 'message' => 'Department not found.'], 404);
        }

        DB::transaction(function () use ($department) {
            $department->employees()->update(['department_id' => null, 'department' => null]);
            $department->delete();
        });

        return response()->json(['status' => true, 'message' => 'Department deleted successfully.']);
    }

    private function rules(?Department $department = null): array
    {
        $presence = $department ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'head_user_id' => ['sometimes', 'nullable', 'integer', $this->employeeExistsRule()],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['integer', 'distinct', $this->employeeExistsRule()],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ];
    }

    private function employeeExistsRule()
    {
        return Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'employee'));
    }

    private function syncEmployees(Department $department, array $employeeIds, bool $creating): void
    {
        if ($department->head_user_id && ! in_array((int) $department->head_user_id, array_map('intval', $employeeIds), true)) {
            $employeeIds[] = (int) $department->head_user_id;
        }

        if (! $creating) {
            $department->employees()->whereNotIn('id', $employeeIds)
                ->update(['department_id' => null, 'department' => null]);
        }
        if ($employeeIds !== []) {
            User::where('role', 'employee')->whereIn('id', $employeeIds)->update([
                'department_id' => $department->id,
                'department' => $department->name,
            ]);
        }
    }

    private function assignEmployee(Department $department, int $employeeId): void
    {
        User::where('role', 'employee')->whereKey($employeeId)->update([
            'department_id' => $department->id,
            'department' => $department->name,
        ]);
    }

    private function load(Department $department): array
    {
        return $this->data($department->load([
            'head:id,employee_id,name,email,avatar,status',
            'employees' => fn ($query) => $query->select([
                'id', 'department_id', 'employee_id', 'name', 'email', 'designation', 'team', 'avatar', 'status',
            ])->orderBy('name'),
        ])->loadCount('employees'));
    }

    private function data(Department $department): array
    {
        $data = $department->toArray();
        $data['employee_count'] = $department->employees_count ?? $department->employees?->count() ?? 0;
        $data['team_count'] = $department->relationLoaded('employees')
            ? $department->employees->pluck('team')->filter()->unique()->count()
            : User::where('department_id', $department->id)->whereNotNull('team')->distinct()->count('team');

        return $data;
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
