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

class DesignationManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Designation::query()->withCount('employees');
        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->orderBy('name')->get()->map(fn (Designation $item) => $this->data($item));

        return response()->json([
            'status' => true,
            'message' => 'Designations retrieved successfully.',
            'total' => $items->count(),
            'data' => $items,
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

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $designation = DB::transaction(function () use ($request) {
            $designation = new Designation();
            $designation->name = trim($request->input('name'));
            $designation->hierarchy_level = strtolower($request->input('hierarchy_level'));
            $designation->skills = json_encode(array_values($request->input('skills', [])));
            $designation->save();

            if ($request->has('employee_ids')) {
                User::whereIn('id', $request->input('employee_ids', []))
                    ->where('role', 'employee')
                    ->update([
                        'designation_id' => $designation->id,
                        'designation' => $designation->name,
                    ]);
            }

            return $designation;
        });

        return response()->json([
            'status' => true,
            'message' => 'Designation created successfully.',
            'data' => $this->load($designation),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $designation = Designation::with(['employees' => fn ($query) => $query
            ->select($this->employeeColumns())->orderBy('name')])
            ->withCount('employees')->find($id);

        if (! $designation) {
            return response()->json(['status' => false, 'message' => 'Designation not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Designation details retrieved successfully.',
            'data' => $this->data($designation),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $designation = Designation::find($id);
        if (! $designation) {
            return response()->json(['status' => false, 'message' => 'Designation not found.'], 404);
        }

        if ($request->has('designation_name') && ! $request->has('name')) {
            $request->merge(['name' => $request->input('designation_name')]);
        }

        $validator = Validator::make($request->all(), $this->rules($designation));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        DB::transaction(function () use ($request, $designation) {
            $oldName = $designation->name;
            if ($request->has('name')) {
                $designation->name = trim($request->input('name'));
            }
            if ($request->has('hierarchy_level')) {
                $designation->hierarchy_level = strtolower($request->input('hierarchy_level'));
            }
            if ($request->has('skills')) {
                $designation->skills = json_encode(array_values($request->input('skills')));
            }
            $designation->save();

            if ($designation->name !== $oldName) {
                $designation->employees()->update(['designation' => $designation->name]);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Designation updated successfully.',
            'data' => $this->load($designation->fresh()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $designation = Designation::withCount('employees')->find($id);
        if (! $designation) {
            return response()->json(['status' => false, 'message' => 'Designation not found.'], 404);
        }
        if ($designation->employees_count > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete a designation while employees are assigned to it.',
            ], 422);
        }

        $designation->delete();

        return response()->json(['status' => true, 'message' => 'Designation deleted successfully.']);
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
            return response()->json(['status' => false, 'message' => 'Employee is not assigned to this designation.'], 422);
        }

        $employee->update(['designation_id' => null, 'designation' => null]);

        return response()->json([
            'status' => true,
            'message' => "Employee '{$employee->name}' removed from designation '{$designation->name}' successfully.",
            'data' => $this->load($designation),
        ]);
    }

    private function rules(?Designation $designation = null): array
    {
        $presence = $designation ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255', Rule::unique('designations', 'name')->ignore($designation?->id)],
            'hierarchy_level' => [$presence, Rule::in(['junior', 'senior', 'manager'])],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['required', 'string', 'max:100', 'distinct'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'employee')),
            ],
        ];
    }

    private function load(Designation $designation): array
    {
        $item = $designation->load(['employees' => fn ($query) => $query
            ->select($this->employeeColumns())->orderBy('name')])->loadCount('employees');

        return $this->data($item);
    }

    private function data(Designation $designation): array
    {
        $data = $designation->toArray();
        $data['skills'] = is_array($designation->skills)
            ? $designation->skills
            : (json_decode((string) $designation->skills, true) ?: []);

        return $data;
    }

    private function employeeColumns(): array
    {
        return ['id', 'designation_id', 'employee_id', 'name', 'email', 'mobile_number', 'avatar', 'status'];
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
