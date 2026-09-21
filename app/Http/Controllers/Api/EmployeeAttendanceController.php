<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignedShift;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeAttendanceController extends Controller
{
    public function checkIn(Request $request): JsonResponse
    {
        $validator = $this->locationValidator($request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $employee = $request->user();
        $now = now();
        $date = $now->toDateString();
        $existing = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', $date)->first();

        if ($existing?->check_in_at) {
            return response()->json([
                'status' => false,
                'message' => 'Attendance has already been marked for today.',
                'data' => $this->attendanceData($existing),
            ], 409);
        }

        $shift = $this->shiftFor($employee, $now);
        $attendanceStatus = 'Present';
        if ($shift && $shift->start_time) {
            $shiftStart = Carbon::parse($date.' '.$shift->start_time);
            $graceMinutes = (int) ($shift->grace_period_minutes ?? 0);
            if ($now->greaterThan($shiftStart->copy()->addMinutes($graceMinutes))) {
                $attendanceStatus = 'Late';
            }
        }

        $attendance = DB::transaction(function () use ($request, $employee, $shift, $now, $date, $attendanceStatus, $existing) {
            $attendance = $existing ?? new Attendance([
                'user_id' => $employee->id,
                'attendance_date' => $date,
            ]);
            $attendance->fill([
                'shift_id' => $shift?->id,
                'check_in_at' => $now,
                'status' => $attendanceStatus,
                'check_in_latitude' => $request->input('latitude'),
                'check_in_longitude' => $request->input('longitude'),
                'check_in_ip' => $request->ip(),
                'notes' => $request->input('notes'),
            ]);
            $attendance->save();

            return $attendance;
        });

        return response()->json([
            'status' => true,
            'message' => 'Attendance marked successfully.',
            'data' => $this->attendanceData($attendance->fresh('shift')),
        ], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $validator = $this->locationValidator($request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $employee = $request->user();
        $now = now();
        $attendance = Attendance::with('shift')
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', $now->toDateString())
            ->first();

        if (! $attendance?->check_in_at) {
            return response()->json([
                'status' => false,
                'message' => 'Please mark attendance before checking out.',
            ], 422);
        }

        if ($attendance->check_out_at) {
            return response()->json([
                'status' => false,
                'message' => 'Checkout has already been marked for today.',
                'data' => $this->attendanceData($attendance),
            ], 409);
        }

        $grossMinutes = max(0, (int) $attendance->check_in_at->diffInMinutes($now));
        $breakMinutes = min($grossMinutes, $this->durationMinutes((string) ($attendance->shift?->break_duration ?? '00:00')));
        $workingMinutes = max(0, $grossMinutes - $breakMinutes);
        $overtimeAfter = (int) ($attendance->shift?->overtime_starts_after_minutes ?? 480);
        $overtimeMinutes = $attendance->shift?->overtime_enabled
            ? max(0, $workingMinutes - $overtimeAfter)
            : 0;
        $halfDayThreshold = (int) ($attendance->shift?->half_day_after_minutes ?? 240);
        $status = $workingMinutes < $halfDayThreshold ? 'Half Day' : $attendance->status;

        $attendance->update([
            'check_out_at' => $now,
            'working_minutes' => $workingMinutes,
            'break_minutes' => $breakMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
            'check_out_latitude' => $request->input('latitude'),
            'check_out_longitude' => $request->input('longitude'),
            'check_out_ip' => $request->ip(),
            'notes' => $request->input('notes', $attendance->notes),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Checkout marked successfully.',
            'data' => $this->attendanceData($attendance->fresh('shift')),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $employee = $request->user();
        $today = now();
        $attendance = Attendance::with('shift')
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();
        $shift = $attendance?->shift ?? $this->shiftFor($employee, $today);
        $todaysBirthdays = $this->birthdayEmployees(0, true);

        return response()->json([
            'status' => true,
            'message' => 'Employee dashboard retrieved successfully.',
            'data' => [
                'greeting' => $this->greeting($today).', '.$employee->name,
                'date' => $today->toDateString(),
                'day' => $today->format('l'),
                'employee' => $employee->only(['id', 'employee_id', 'name', 'email', 'avatar', 'designation', 'department']),
                'current_shift' => $this->shiftData($shift, $today),
                'attendance' => $attendance ? $this->attendanceData($attendance) : [
                    'check_in' => null,
                    'check_out' => null,
                    'status' => 'Not Marked',
                ],
                'todays_birthdays' => $todaysBirthdays,
                'todays_summary' => [
                    'working_minutes' => (int) ($attendance?->working_minutes ?? 0),
                    'working_hours' => $this->readableMinutes((int) ($attendance?->working_minutes ?? 0)),
                    'break_minutes' => (int) ($attendance?->break_minutes ?? 0),
                    'break_hours' => $this->readableMinutes((int) ($attendance?->break_minutes ?? 0)),
                    'overtime_minutes' => (int) ($attendance?->overtime_minutes ?? 0),
                    'overtime' => $this->readableMinutes((int) ($attendance?->overtime_minutes ?? 0)),
                    'status' => $attendance?->status ?? 'Not Marked',
                ],
            ],
        ]);
    }

    public function upcomingBirthdays(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $days = (int) $request->input('days', 30);
        $birthdays = $this->birthdayEmployees($days);

        return response()->json([
            'status' => true,
            'message' => 'Upcoming birthdays retrieved successfully.',
            'days' => $days,
            'total' => $birthdays->count(),
            'data' => $birthdays,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $employee = $request->user();
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $attendances = Attendance::with('shift')
            ->where('user_id', $employee->id)
            ->whereBetween('attendance_date', [$month->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn (Attendance $attendance) => $attendance->attendance_date->toDateString());
        $leaves = LeaveRequest::where('user_id', $employee->id)
            ->where('status', 'Approved')
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $month)
            ->get();
        $shift = $this->shiftFor($employee, $month) ?? $employee->assignedShift;
        $calendar = collect();
        $counts = ['present' => 0, 'half_day' => 0, 'absent' => 0, 'leave' => 0, 'weekend' => 0];
        $workingDays = 0;

        for ($date = $month->copy(); $date->lte($end); $date->addDay()) {
            $record = $attendances->get($date->toDateString());
            $isLeave = $leaves->contains(fn (LeaveRequest $leave) => $date->betweenIncluded($leave->from_date, $leave->to_date));
            $isWorkingDay = $this->isWorkingDay($shift, $date);
            $category = 'upcoming';
            $label = 'Upcoming';

            if (! $isWorkingDay) {
                $category = 'weekend';
                $label = 'Weekend';
            } elseif ($isLeave) {
                $category = 'leave';
                $label = 'Leave';
            } elseif ($record) {
                $category = $record->status === 'Half Day' ? 'half_day' : 'present';
                $label = $record->status;
            } elseif ($date->lt(now()->startOfDay())) {
                $category = 'absent';
                $label = 'Absent';
            } elseif ($date->isToday()) {
                $category = 'not_marked';
                $label = 'Not Marked';
            }

            if ($date->lte(now()->startOfDay())) {
                if (array_key_exists($category, $counts)) {
                    $counts[$category]++;
                }
                if ($isWorkingDay) {
                    $workingDays++;
                }
            }

            $calendar->push([
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'category' => $category,
                'status' => $label,
                'check_in' => $record?->check_in_at?->format('h:i A'),
                'check_out' => $record?->check_out_at?->format('h:i A'),
                'working_minutes' => (int) ($record?->working_minutes ?? 0),
                'working_hours' => $this->readableMinutes((int) ($record?->working_minutes ?? 0)),
            ]);
        }

        $attendanceUnits = $counts['present'] + ($counts['half_day'] * 0.5);
        $percentage = $workingDays > 0 ? round(($attendanceUnits / $workingDays) * 100, 2) : 0;

        return response()->json([
            'status' => true,
            'message' => 'Attendance history retrieved successfully.',
            'data' => [
                'month' => $month->format('Y-m'),
                'month_label' => $month->format('F Y'),
                'summary' => [
                    ...$counts,
                    'working_days' => $workingDays,
                    'attendance_percentage' => $percentage,
                ],
                'calendar' => $calendar,
                'recent_records' => $calendar->whereNotNull('check_in')->sortByDesc('date')->values(),
            ],
        ]);
    }

    private function locationValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }

    private function shiftFor(User $employee, Carbon $date): ?Shift
    {
        $assignment = AssignedShift::with('shift')
            ->where('user_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        return $assignment?->shift ?? Shift::find($employee->assigned_shift_id);
    }

    private function attendanceData(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'date' => $attendance->attendance_date?->toDateString(),
            'check_in' => $attendance->check_in_at?->format('h:i A'),
            'check_in_at' => $attendance->check_in_at?->toIso8601String(),
            'check_out' => $attendance->check_out_at?->format('h:i A'),
            'check_out_at' => $attendance->check_out_at?->toIso8601String(),
            'status' => $attendance->status,
            'working_minutes' => (int) $attendance->working_minutes,
            'working_hours' => $this->readableMinutes((int) $attendance->working_minutes),
            'break_minutes' => (int) $attendance->break_minutes,
            'break_hours' => $this->readableMinutes((int) $attendance->break_minutes),
            'overtime_minutes' => (int) $attendance->overtime_minutes,
            'overtime' => $this->readableMinutes((int) $attendance->overtime_minutes),
            'shift' => $this->shiftData($attendance->shift, $attendance->attendance_date ?? now()),
        ];
    }

    private function shiftData(?Shift $shift, Carbon $date): ?array
    {
        if (! $shift) {
            return null;
        }

        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'code' => $shift->code,
            'start_time' => Carbon::parse($shift->start_time)->format('h:i A'),
            'end_time' => Carbon::parse($shift->end_time)->format('h:i A'),
            'date' => $date->toDateString(),
            'is_ongoing' => $this->shiftIsOngoing($shift, $date),
        ];
    }

    private function shiftIsOngoing(Shift $shift, Carbon $date): bool
    {
        $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return now()->betweenIncluded($start, $end);
    }

    private function birthdayEmployees(int $days, bool $todayOnly = false): Collection
    {
        $today = now()->startOfDay();

        return User::where('role', 'employee')
            ->whereNotNull('date_of_birth')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'Inactive');
            })
            ->get(['id', 'employee_id', 'name', 'date_of_birth', 'designation', 'avatar'])
            ->map(function (User $employee) use ($today) {
                $birthday = Carbon::parse($employee->date_of_birth);
                $nextBirthday = $this->birthdayInYear($birthday, $today->year);
                if ($nextBirthday->lt($today)) {
                    $nextBirthday = $this->birthdayInYear($birthday, $today->year + 1);
                }
                $daysUntil = (int) $today->diffInDays($nextBirthday);

                return [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'name' => $employee->name,
                    'designation' => $employee->designation,
                    'avatar' => $employee->avatar,
                    'birthday' => $nextBirthday->toDateString(),
                    'birthday_label' => $daysUntil === 0 ? 'Today' : $nextBirthday->format('d M'),
                    'days_until' => $daysUntil,
                    'is_today' => $daysUntil === 0,
                ];
            })
            ->filter(fn (array $item) => $todayOnly ? $item['is_today'] : $item['days_until'] <= $days)
            ->sortBy(['days_until', 'name'])
            ->values();
    }

    private function birthdayInYear(Carbon $birthday, int $year): Carbon
    {
        try {
            return Carbon::createSafe($year, $birthday->month, $birthday->day)->startOfDay();
        } catch (\Throwable) {
            return Carbon::create($year, $birthday->month, 1)->endOfMonth()->startOfDay();
        }
    }

    private function isWorkingDay(?Shift $shift, Carbon $date): bool
    {
        $workingDays = is_array($shift?->working_days)
            ? $shift->working_days
            : (json_decode((string) $shift?->working_days, true) ?: []);
        if ($workingDays !== []) {
            $day = collect($workingDays)->firstWhere('day', $date->format('l'));

            return (bool) ($day['enabled'] ?? false);
        }

        return ! $date->isWeekend();
    }

    private function durationMinutes(string $duration): int
    {
        if (! str_contains($duration, ':')) {
            return (int) $duration;
        }
        [$hours, $minutes] = array_pad(explode(':', $duration, 2), 2, 0);

        return ((int) $hours * 60) + (int) $minutes;
    }

    private function readableMinutes(int $minutes): string
    {
        return sprintf('%02dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    private function greeting(Carbon $time): string
    {
        return match (true) {
            $time->hour < 12 => 'Good Morning',
            $time->hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };
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
