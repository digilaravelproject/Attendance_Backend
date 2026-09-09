<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftRotation;
use App\Models\ShiftRotationUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShiftRotationController extends Controller
{
    /**
     * List schedule shift rotations with statistics for date filter (Screenshot 3 - Part 2).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShiftRotation::query()->with(['shifts', 'users']);

        // Month or Date range filtering
        if ($request->filled('month')) {
            $month = $request->input('month'); // format: YYYY-MM
            try {
                $startDate = Carbon::parse($month . '-01')->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::parse($month . '-01')->endOfMonth()->format('Y-m-d');
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate]);
                });
            } catch (\Throwable $e) {
                // ignore if invalid month format
            }
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->where('start_date', '>=', $request->input('start_date'))
                  ->where('end_date', '<=', $request->input('end_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rotations = $query->orderBy('start_date', 'desc')->get();

        // Calculate statistics (Screenshot 3 - Part 2 top stats cards)
        $totalRotations = $rotations->count();
        $activeRotations = $rotations->where('status', 'Active')->count();
        $completedRotations = $rotations->where('status', 'Completed')->count();
        $upcomingRotations = $rotations->where('status', 'Upcoming')->count();

        // Unique staff count across matching rotations
        $uniqueStaffCount = DB::table('shift_rotation_users')
            ->whereIn('shift_rotation_id', $rotations->pluck('id'))
            ->distinct('user_id')
            ->count('user_id');

        $formattedData = $rotations->map(function ($rotation) {
            return [
                'id' => $rotation->id,
                'name' => $rotation->name,
                'start_date' => $rotation->start_date?->format('Y-m-d'),
                'end_date' => $rotation->end_date?->format('Y-m-d'),
                'formatted_date_range' => $rotation->start_date && $rotation->end_date 
                    ? $rotation->start_date->format('d M') . ' - ' . $rotation->end_date->format('d M Y')
                    : '',
                'frequency_cycle' => $rotation->frequency_cycle,
                'status' => $rotation->status,
                'shifts_included_count' => $rotation->shifts->count(),
                'employees_assigned_count' => $rotation->users->count(),
                'shifts' => $rotation->shifts->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'shift_type' => $s->shift_type,
                    'timing' => "{$s->start_time} - {$s->end_time}",
                ]),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Shift rotations retrieved successfully.',
            'statistics' => [
                'total_rotations' => $totalRotations,
                'active' => $activeRotations,
                'completed' => $completedRotations,
                'upcoming' => $upcomingRotations,
                'unique_staff' => $uniqueStaffCount,
            ],
            'total' => $formattedData->count(),
            'data' => $formattedData,
        ]);
    }

    /**
     * Create schedule shift rotation (Screenshot 3 - Part 1).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'frequency_cycle' => 'nullable|string|max:100',
            'status' => ['sometimes', 'string', Rule::in(['Upcoming', 'Active', 'Completed'])],
            'initial_status' => ['sometimes', 'string', Rule::in(['Upcoming', 'Active', 'Completed'])],
            'shift_ids' => 'required|array|min:1',
            'shift_ids.*' => 'integer|exists:shifts,id',
            'employee_ids' => 'sometimes|array',
            'employee_ids.*' => 'integer|exists:users,id',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $status = $data['status'] ?? $data['initial_status'] ?? 'Upcoming';

        $rotation = ShiftRotation::create([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'frequency_cycle' => $data['frequency_cycle'] ?? 'Weekly',
            'status' => $status,
        ]);

        // Attach shifts
        $rotation->shifts()->sync($data['shift_ids']);

        // Auto-assign staff if provided
        $userIds = $data['employee_ids'] ?? $data['user_ids'] ?? [];
        if (!empty($userIds)) {
            $shiftIds = $data['shift_ids'];
            $pivotData = [];
            foreach ($userIds as $idx => $uId) {
                // assign round-robin shift to users
                $assignedShiftId = $shiftIds[$idx % count($shiftIds)];
                $pivotData[$uId] = ['shift_id' => $assignedShiftId];
            }
            $rotation->users()->sync($pivotData);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift rotation scheduled successfully.',
            'data' => $this->getRotationDetails($rotation->id),
        ], 201);
    }

    /**
     * View schedule shift rotation with user details & search by ID (Screenshot 3 - Part 3).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $rotation = ShiftRotation::with(['shifts'])->find($id);

        if (!$rotation) {
            return response()->json(['status' => false, 'message' => 'Shift rotation not found.'], 404);
        }

        $search = trim((string) $request->input('search', $request->input('query', '')));

        $rotationUsersQuery = ShiftRotationUser::where('shift_rotation_id', $rotation->id)
            ->with(['user:id,name,email,employee_id,mobile_number,avatar,designation,role', 'shift:id,name,shift_type,start_time,end_time']);

        if ($search !== '') {
            $rotationUsersQuery->whereHas('user', function ($b) use ($search) {
                $b->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        $assignedStaff = $rotationUsersQuery->get()->map(function ($pivot) {
            return [
                'user_id' => $pivot->user_id,
                'name' => $pivot->user?->name,
                'email' => $pivot->user?->email,
                'avatar' => $pivot->user?->avatar,
                'designation' => $pivot->user?->designation ?? 'Staff Member',
                'role' => $pivot->user?->role,
                'assigned_shift' => [
                    'id' => $pivot->shift?->id,
                    'name' => $pivot->shift?->name ?? 'General Shift',
                    'shift_type' => $pivot->shift?->shift_type,
                    'timing' => $pivot->shift ? "{$pivot->shift->start_time} - {$pivot->shift->end_time}" : null,
                ],
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Shift rotation details retrieved successfully.',
            'data' => [
                'id' => $rotation->id,
                'name' => $rotation->name,
                'status' => $rotation->status,
                'start_date' => $rotation->start_date?->format('Y-m-d'),
                'end_date' => $rotation->end_date?->format('Y-m-d'),
                'formatted_date_range' => $rotation->start_date && $rotation->end_date 
                    ? $rotation->start_date->format('d M') . ' - ' . $rotation->end_date->format('d M Y')
                    : '',
                'frequency_cycle' => $rotation->frequency_cycle,
                'total_assigned_staff' => $assignedStaff->count(),
                'shifts_included' => $rotation->shifts->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'shift_type' => $s->shift_type,
                    'timing' => "{$s->start_time} - {$s->end_time}",
                ]),
                'assigned_staff' => $assignedStaff,
            ],
        ]);
    }

    /**
     * Update schedule shift rotation.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $rotation = ShiftRotation::find($id);

        if (!$rotation) {
            return response()->json(['status' => false, 'message' => 'Shift rotation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
            'frequency_cycle' => 'nullable|string|max:100',
            'status' => ['sometimes', 'string', Rule::in(['Upcoming', 'Active', 'Completed'])],
            'shift_ids' => 'sometimes|array',
            'shift_ids.*' => 'integer|exists:shifts,id',
            'employee_ids' => 'sometimes|array',
            'employee_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $rotation->update($request->only(['name', 'start_date', 'end_date', 'frequency_cycle', 'status']));

        if (!empty($data['shift_ids'])) {
            $rotation->shifts()->sync($data['shift_ids']);
        }

        if (isset($data['employee_ids'])) {
            $shiftIds = $rotation->shifts()->pluck('shifts.id')->toArray();
            $pivotData = [];
            foreach ($data['employee_ids'] as $idx => $uId) {
                $assignedShiftId = !empty($shiftIds) ? $shiftIds[$idx % count($shiftIds)] : null;
                $pivotData[$uId] = ['shift_id' => $assignedShiftId];
            }
            $rotation->users()->sync($pivotData);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift rotation updated successfully.',
            'data' => $this->getRotationDetails($rotation->id),
        ]);
    }

    /**
     * Assign user(s) to shift rotation.
     */
    public function assignUsers(Request $request, string $id): JsonResponse
    {
        $rotation = ShiftRotation::find($id);

        if (!$rotation) {
            return response()->json(['status' => false, 'message' => 'Shift rotation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required_without:employee_ids|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'employee_ids' => 'required_without:user_ids|array|min:1',
            'employee_ids.*' => 'integer|exists:users,id',
            'shift_id' => 'nullable|integer|exists:shifts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userIds = $request->input('user_ids', $request->input('employee_ids', []));
        $shiftId = $request->input('shift_id');

        $shiftIds = $rotation->shifts()->pluck('shifts.id')->toArray();

        foreach ($userIds as $idx => $uId) {
            $assignedShiftId = $shiftId ?? (!empty($shiftIds) ? $shiftIds[$idx % count($shiftIds)] : null);

            ShiftRotationUser::updateOrCreate(
                [
                    'shift_rotation_id' => $rotation->id,
                    'user_id' => $uId,
                ],
                [
                    'shift_id' => $assignedShiftId,
                ]
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Staff assigned to rotation successfully.',
            'data' => $this->getRotationDetails($rotation->id),
        ]);
    }

    /**
     * Remove user from schedule shift rotation (Screenshot 3 - Part 3).
     */
    public function removeUser(Request $request, string $id, ?string $userId = null): JsonResponse
    {
        $rotation = ShiftRotation::find($id);

        if (!$rotation) {
            return response()->json(['status' => false, 'message' => 'Shift rotation not found.'], 404);
        }

        $targetUserId = $userId ?? $request->input('user_id', $request->input('employee_id'));

        if (!$targetUserId) {
            return response()->json(['status' => false, 'message' => 'User ID is required to remove user from rotation.'], 422);
        }

        $deleted = ShiftRotationUser::where('shift_rotation_id', $rotation->id)
            ->where('user_id', $targetUserId)
            ->delete();

        if (!$deleted) {
            return response()->json(['status' => false, 'message' => 'User is not assigned to this rotation.'], 444 ?? 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'User removed from shift rotation successfully.',
        ]);
    }

    /**
     * Delete schedule shift rotation.
     */
    public function destroy(string $id): JsonResponse
    {
        $rotation = ShiftRotation::find($id);

        if (!$rotation) {
            return response()->json(['status' => false, 'message' => 'Shift rotation not found.'], 404);
        }

        $rotation->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shift rotation deleted successfully.',
        ]);
    }

    /**
     * Helper to get full rotation payload.
     */
    private function getRotationDetails(int $id): array
    {
        $rotation = ShiftRotation::with(['shifts', 'users'])->find($id);
        if (!$rotation) {
            return [];
        }

        return [
            'id' => $rotation->id,
            'name' => $rotation->name,
            'status' => $rotation->status,
            'start_date' => $rotation->start_date?->format('Y-m-d'),
            'end_date' => $rotation->end_date?->format('Y-m-d'),
            'formatted_date_range' => $rotation->start_date && $rotation->end_date 
                ? $rotation->start_date->format('d M') . ' - ' . $rotation->end_date->format('d M Y')
                : '',
            'frequency_cycle' => $rotation->frequency_cycle,
            'shifts' => $rotation->shifts->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'shift_type' => $s->shift_type,
            ]),
            'assigned_staff_count' => $rotation->users->count(),
        ];
    }
}
