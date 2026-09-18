<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignedShift;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssignedShiftController extends Controller
{
    /**
     * List all assigned shifts with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssignedShift::query()
            ->with(['shift', 'employee:id,name,email,employee_id,mobile_number,avatar,designation,designation_id', 'assigner:id,name']);

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->input('shift_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->filled('employee_id') || $request->filled('user_id')) {
            $userId = $request->input('employee_id', $request->input('user_id'));
            $query->where('user_id', $userId);
        }

        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->whereHas('employee', function ($b) use ($search) {
                $b->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $assignedShifts = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Assigned shifts retrieved successfully.',
            'total' => $assignedShifts->count(),
            'data' => $assignedShifts,
        ]);
    }

    /**
     * Assign shift to employees or by department (Screenshot 2).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shift_id' => 'required|integer|exists:shifts,id',
            'date' => 'required_without:dates|date_format:Y-m-d',
            'dates' => 'sometimes|array',
            'dates.*' => 'date_format:Y-m-d',
            'employee_ids' => 'required_without_all:department_id,designation_id|array',
            'employee_ids.*' => 'integer|exists:users,id',
            'department_id' => 'sometimes|nullable',
            'designation_id' => 'sometimes|nullable|integer|exists:designations,id',
            'assignment_type' => ['sometimes', 'string', Rule::in(['By Employee', 'By Department'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $shift = Shift::findOrFail($data['shift_id']);
        // assigned_by references users; separate admin accounts are not stored in that table.
        $assignedBy = $request->user() instanceof User ? $request->user()->id : null;

        // Resolve dates array
        $dates = [];
        if (!empty($data['dates'])) {
            $dates = $data['dates'];
        } elseif (!empty($data['date'])) {
            $dates = [$data['date']];
        }

        // Resolve employee IDs
        $userIds = [];
        if (!empty($data['employee_ids'])) {
            $userIds = $data['employee_ids'];
        } elseif (!empty($data['designation_id'])) {
            $userIds = User::where('role', 'employee')
                ->where('designation_id', $data['designation_id'])
                ->pluck('id')
                ->toArray();
        }

        if (empty($userIds)) {
            return response()->json([
                'status' => false,
                'message' => 'No eligible employees found for shift assignment.',
            ], 422);
        }

        $assignmentType = $data['assignment_type'] ?? (!empty($data['designation_id']) ? 'By Department' : 'By Employee');
        $createdRecords = [];

        foreach ($dates as $dateStr) {
            foreach ($userIds as $userId) {
                $assignedShift = AssignedShift::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'date' => $dateStr,
                    ],
                    [
                        'shift_id' => $shift->id,
                        'assignment_type' => $assignmentType,
                        'assigned_by' => $assignedBy,
                        'status' => 'Assigned',
                    ]
                );

                $createdRecords[] = $assignedShift->id;
            }
        }

        $assignedList = AssignedShift::with(['shift', 'employee:id,name,email,employee_id,mobile_number,avatar,designation'])
            ->whereIn('id', $createdRecords)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Shift assigned successfully to ' . count($userIds) . ' employee(s).',
            'total_assigned' => count($assignedList),
            'data' => $assignedList,
        ], 201);
    }

    /**
     * View assigned shift details by ID.
     */
    public function show(string $id): JsonResponse
    {
        $assignedShift = AssignedShift::with(['shift', 'employee', 'assigner:id,name'])->find($id);
        if (!$assignedShift) {
            return response()->json(['status' => false, 'message' => 'Assigned shift not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Assigned shift details retrieved successfully.',
            'data' => $assignedShift,
        ]);
    }

    /**
     * Update assigned shift by ID.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $assignedShift = AssignedShift::find($id);
        if (!$assignedShift) {
            return response()->json(['status' => false, 'message' => 'Assigned shift not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'shift_id' => 'sometimes|integer|exists:shifts,id',
            'date' => 'sometimes|date_format:Y-m-d',
            'user_id' => 'sometimes|integer|exists:users,id',
            'status' => ['sometimes', 'string', Rule::in(['Assigned', 'Completed', 'Absent', 'Leave'])],
            'assignment_type' => ['sometimes', 'string', Rule::in(['By Employee', 'By Department'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $assignedShift->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Assigned shift updated successfully.',
            'data' => $assignedShift->fresh(['shift', 'employee', 'assigner:id,name']),
        ]);
    }

    /**
     * Delete assigned shift by ID.
     */
    public function destroy(string $id): JsonResponse
    {
        $assignedShift = AssignedShift::find($id);
        if (!$assignedShift) {
            return response()->json(['status' => false, 'message' => 'Assigned shift not found.'], 404);
        }

        $assignedShift->delete();

        return response()->json([
            'status' => true,
            'message' => 'Assigned shift deleted successfully.',
        ]);
    }
}
