<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', 'employee')
            ->with('designationDetails:id,name,hierarchy_level');

        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhereHas('designationDetails', fn ($designation) => $designation
                        ->where('name', 'like', "%{$search}%"));
            });
        }

        $employees = $query->latest()->get($this->employeeColumns());

        return response()->json([
            'status' => true,
            'message' => 'Employees retrieved successfully.',
            'total' => $employees->count(),
            'data' => $employees,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required_without:search|string|max:255',
            'search' => 'required_without:query|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $designation = Designation::find($data['designation_id']);

        $employee = User::create([
            ...Arr::except($data, ['password', 'designation_id']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password'] ?? Str::random(32)),
            'role' => 'employee',
            'designation_id' => $designation->id,
            'designation' => $designation->name,
            'phone' => $data['mobile_number'],
            'status' => $data['status'] ?? 'Active',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Employee created successfully.',
            'data' => $this->loadEmployee($employee),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = User::where('role', 'employee')
            ->with('designationDetails:id,name,hierarchy_level')
            ->find($id, $this->employeeColumns());

        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Employee details retrieved successfully.',
            'data' => $employee,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $employee = User::where('role', 'employee')->find($id);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($employee));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower(trim($data['email']));
        }
        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
        }
        if (array_key_exists('mobile_number', $data)) {
            $data['phone'] = $data['mobile_number'];
        }
        if (array_key_exists('designation_id', $data)) {
            $data['designation'] = Designation::findOrFail($data['designation_id'])->name;
        }

        $employee->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Employee updated successfully.',
            'data' => $this->loadEmployee($employee),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = User::where('role', 'employee')->find($id);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        $employee->tokens()->delete();
        $employee->delete();

        return response()->json([
            'status' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }

    private function rules(?User $employee = null): array
    {
        $presence = $employee ? 'sometimes' : 'required';

        return [
            'employee_id' => [$presence, 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($employee?->id)],
            'name' => [$presence, 'string', 'max:255'],
            'mobile_number' => [$presence, 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'email' => [$presence, 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->id)],
            'emergency_contact' => [$presence, 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'address' => [$presence, 'string', 'max:2000'],
            'designation_id' => [$presence, 'integer', 'exists:designations,id'],
            'monthly_salary' => [$presence, 'numeric', 'min:0', 'max:9999999999.99'],
            'date_of_joining' => [$presence, 'date_format:Y-m-d'],
            'skills' => [$presence, 'array', 'min:1'],
            'skills.*' => ['string', 'max:100', 'distinct'],
            'status' => ['sometimes', 'string', Rule::in(['Active', 'Inactive'])],
            'avatar' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ];
    }

    private function employeeColumns(): array
    {
        return [
            'id', 'designation_id', 'employee_id', 'name', 'email', 'role', 'mobile_number',
            'phone', 'emergency_contact', 'address', 'designation', 'monthly_salary',
            'date_of_joining', 'skills', 'avatar', 'status', 'created_at', 'updated_at',
        ];
    }

    private function loadEmployee(User $employee): User
    {
        return User::query()
            ->select($this->employeeColumns())
            ->with('designationDetails:id,name,hierarchy_level')
            ->findOrFail($employee->id);
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
