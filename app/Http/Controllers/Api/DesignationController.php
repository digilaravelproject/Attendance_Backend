<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Designation::query()->withCount('employees');
        $search = trim((string) $request->input('search', $request->input('query', '')));

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $designations = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'message' => 'Designations retrieved successfully.',
            'total' => $designations->count(),
            'data' => $designations,
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
        if ($request->has('designation_name') && ! $request->has('name')) {
            $request->merge(['name' => $request->input('designation_name')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:designations,name',
            'hierarchy_level' => ['required', Rule::in(['junior', 'senior', 'manager'])],
            'employee_ids' => 'sometimes|array',
            'employee_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'employee')),
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $designation = DB::transaction(function () use ($request) {
            $designation = Designation::create([
                'name' => trim($request->input('name')),
                'hierarchy_level' => strtolower($request->input('hierarchy_level')),
            ]);

            if ($request->has('employee_ids')) {
                User::whereIn('id', $request->input('employee_ids', []))->update([
                    'designation_id' => $designation->id,
                    'designation' => $designation->name,
                ]);
            }

            return $designation;
        });

        return response()->json([
            'status' => true,
            'message' => 'Designation created successfully.',
            'data' => $this->loadDesignation($designation),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $designation = Designation::with(['employees' => fn ($query) => $query
            ->select($this->employeeColumns())
            ->orderBy('name')])
            ->withCount('employees')
            ->find($id);

        if (! $designation) {
            return response()->json(['status' => false, 'message' => 'Designation not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Designation details retrieved successfully.',
            'data' => $designation,
        ]);
    }

    public function removeEmployee(string $id, string $employeeId): JsonResponse
    {
        $designation = Designation::find($id);

        if (! $designation) {
            return response()->json(['status' => false, 'message' => 'Designation not found.'], 404);
        }

        $employee = User::where('role', 'employee')->find($employeeId);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        if ((int) $employee->designation_id !== (int) $designation->id) {
            return response()->json([
                'status' => false,
                'message' => 'Employee is not assigned to this designation.',
            ], 422);
        }

        $employee->update(['designation_id' => null, 'designation' => null]);

        return response()->json([
            'status' => true,
            'message' => "Employee '{$employee->name}' removed from designation '{$designation->name}' successfully.",
            'data' => $this->loadDesignation($designation),
        ]);
    }

    private function loadDesignation(Designation $designation): Designation
    {
        return $designation->load(['employees' => fn ($query) => $query
            ->select($this->employeeColumns())
            ->orderBy('name')])->loadCount('employees');
    }

    private function employeeColumns(): array
    {
        return ['id', 'designation_id', 'employee_id', 'name', 'email', 'mobile_number', 'avatar', 'status'];
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
