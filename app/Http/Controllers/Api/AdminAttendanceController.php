<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['all', 'present', 'absent', 'on_leave', 'half_day', 'late'])],
            'sort' => ['sometimes', Rule::in(['name', 'employee_id', 'check_in', 'status'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->input('date', now()->toDateString()))->startOfDay();
        $search = trim((string) $request->input('search', ''));
        $employees = User::query()->where('role', 'employee')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'Inactive'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%"));
            })
            ->get(['id', 'employee_id', 'name', 'avatar', 'designation', 'department']);
        $attendance = Attendance::whereDate('attendance_date', $date)->get()->keyBy('user_id');
        $leaveUserIds = LeaveRequest::where('status', 'Approved')
            ->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)
            ->pluck('user_id')->flip();

        $rows = $employees->map(function (User $employee) use ($attendance, $leaveUserIds, $date) {
            $record = $attendance->get($employee->id);
            $category = match (true) {
                $record?->status === 'Half Day' => 'half_day',
                $record?->status === 'Late' => 'late',
                (bool) $record => 'present',
                $leaveUserIds->has($employee->id) => 'on_leave',
                default => 'absent',
            };

            return [
                'employee' => $employee->only(['id', 'employee_id', 'name', 'avatar', 'designation', 'department']),
                'date' => $date->toDateString(),
                'attendance_id' => $record?->id,
                'status' => $record?->status ?? ($category === 'on_leave' ? 'On Leave' : 'Absent'),
                'category' => $category,
                'check_in' => $record?->check_in_at?->format('h:i A'),
                'check_out' => $record?->check_out_at?->format('h:i A'),
                'working_minutes' => (int) ($record?->working_minutes ?? 0),
                'working_hours' => sprintf('%02dh %02dm', intdiv((int) ($record?->working_minutes ?? 0), 60), (int) ($record?->working_minutes ?? 0) % 60),
            ];
        });

        $summary = [
            'total_employees' => $rows->count(),
            'present' => $rows->whereIn('category', ['present', 'late', 'half_day'])->count(),
            'absent' => $rows->where('category', 'absent')->count(),
            'on_leave' => $rows->where('category', 'on_leave')->count(),
        ];
        $summary['present_percentage'] = $summary['total_employees'] ? round($summary['present'] / $summary['total_employees'] * 100, 2) : 0;
        $summary['absent_percentage'] = $summary['total_employees'] ? round($summary['absent'] / $summary['total_employees'] * 100, 2) : 0;

        if ($request->input('status', 'all') !== 'all') {
            $rows = $rows->where('category', $request->input('status'))->values();
        }
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $rows = $rows->sortBy(function (array $row) use ($sort) {
            return match ($sort) {
                'employee_id' => $row['employee']['employee_id'],
                'check_in' => $row['check_in'] ?? '99:99',
                'status' => $row['status'],
                default => $row['employee']['name'],
            };
        }, SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc')->values();

        $dateCards = collect(range(-4, 0))->map(function (int $offset) use ($date) {
            $cardDate = $date->copy()->addDays($offset);

            return [
                'date' => $cardDate->toDateString(),
                'day' => $cardDate->format('D'),
                'attendance_count' => Attendance::whereDate('attendance_date', $cardDate)->whereNotNull('check_in_at')->count(),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Employee attendance retrieved successfully.',
            'data' => ['date' => $date->toDateString(), 'summary' => $summary, 'date_cards' => $dateCards, 'employees' => $rows],
        ]);
    }
}
