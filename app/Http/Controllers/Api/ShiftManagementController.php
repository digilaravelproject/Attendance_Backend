<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShiftManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query();
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('shift_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $items = $query->latest()->get()->map(fn (Shift $shift) => $this->data($shift));

        return response()->json([
            'status' => true,
            'message' => 'Shifts retrieved successfully.',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->prepareAliases($request);
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $shift = DB::transaction(function () use ($request) {
                $shift = new Shift();
                $this->fillShift($shift, $request, true);
                $shift->save();
                $this->syncEmployees($shift, $request->input('employee_ids', []));

                return $shift;
            });
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['time' => [$exception->getMessage()]]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift created successfully.',
            'data' => $this->data($shift->fresh()),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $shift = Shift::find($id);
        if (! $shift) {
            return response()->json(['status' => false, 'message' => 'Shift not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift details retrieved successfully.',
            'data' => $this->data($shift),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $shift = Shift::find($id);
        if (! $shift) {
            return response()->json(['status' => false, 'message' => 'Shift not found.'], 404);
        }

        $this->prepareAliases($request);
        $validator = Validator::make($request->all(), $this->rules($shift));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            DB::transaction(function () use ($request, $shift) {
                $this->fillShift($shift, $request, false);
                $shift->save();
                if ($request->has('employee_ids')) {
                    $this->syncEmployees($shift, $request->input('employee_ids', []));
                }
            });
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['time' => [$exception->getMessage()]]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift updated successfully.',
            'data' => $this->data($shift->fresh()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $shift = Shift::find($id);
        if (! $shift) {
            return response()->json(['status' => false, 'message' => 'Shift not found.'], 404);
        }

        $shift->delete();

        return response()->json(['status' => true, 'message' => 'Shift deleted successfully.']);
    }

    public function overview(Request $request): JsonResponse
    {
        return app(ShiftController::class)->overview($request);
    }

    private function rules(?Shift $shift = null): array
    {
        $presence = $shift ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('shifts', 'code')->ignore($shift?->id)],
            'shift_type' => [$presence, 'string', 'max:100'],
            'start_time' => [$presence, 'string', 'max:50'],
            'end_time' => [$presence, 'string', 'max:50'],
            'cross_midnight' => ['sometimes', 'boolean'],
            'breaks_enabled' => ['sometimes', 'boolean'],
            'breaks' => ['sometimes', 'array'],
            'breaks.*.name' => ['required_with:breaks', 'string', 'max:100'],
            'breaks.*.type' => ['required_with:breaks', Rule::in(['Paid', 'Unpaid'])],
            'breaks.*.start_time' => ['required_with:breaks', 'string', 'max:50'],
            'breaks.*.end_time' => ['required_with:breaks', 'string', 'max:50'],
            'breaks.*.duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'break_duration' => ['sometimes', 'nullable', 'string', 'max:50'],
            'grace_period_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'late_after_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'minimum_working_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'early_leaving_allowed' => ['sometimes', 'boolean'],
            'auto_mark_late' => ['sometimes', 'boolean'],
            'auto_mark_half_day' => ['sometimes', 'boolean'],
            'late_threshold_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'half_day_after_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'overtime_enabled' => ['sometimes', 'boolean'],
            'overtime_starts_after_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'minimum_overtime_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'overtime_calculation' => ['sometimes', Rule::in(['Hourly', 'Fixed', 'Multiplier'])],
            'overtime_approval_required' => ['sometimes', 'boolean'],
            'working_days' => ['sometimes', 'array'],
            'working_days.*.day' => ['required_with:working_days', Rule::in([
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
            ])],
            'working_days.*.enabled' => ['required_with:working_days', 'boolean'],
            'working_days.*.start_time' => ['nullable', 'string', 'max:50'],
            'working_days.*.end_time' => ['nullable', 'string', 'max:50'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => [
                'integer', 'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'employee')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ];
    }

    private function prepareAliases(Request $request): void
    {
        $updates = [];
        if (! $request->filled('code') && $request->filled('name')) {
            $updates['code'] = strtoupper(Str::slug($request->input('name'), '_'));
        }
        if (is_bool($request->input('status'))) {
            $updates['status'] = $request->boolean('status') ? 'Active' : 'Inactive';
        }
        if (! $request->has('grace_period_minutes') && $request->filled('grace_time_late')) {
            $updates['grace_period_minutes'] = $this->durationMinutes($request->input('grace_time_late'));
        }
        if (! $request->has('overtime_starts_after_minutes') && $request->filled('overtime_after')) {
            $updates['overtime_starts_after_minutes'] = $this->durationMinutes($request->input('overtime_after'));
        }
        $request->merge($updates);
    }

    private function fillShift(Shift $shift, Request $request, bool $creating): void
    {
        foreach (['name', 'code', 'shift_type', 'description', 'status'] as $field) {
            if ($request->has($field)) {
                $shift->{$field} = $request->input($field);
            }
        }

        foreach (['start_time', 'end_time'] as $field) {
            if ($request->has($field)) {
                $shift->{$field} = $this->normalTime($request->input($field));
            }
        }

        $booleanDefaults = [
            'cross_midnight' => false,
            'breaks_enabled' => true,
            'early_leaving_allowed' => false,
            'auto_mark_late' => true,
            'auto_mark_half_day' => true,
            'overtime_enabled' => false,
            'overtime_approval_required' => true,
        ];
        foreach ($booleanDefaults as $field => $default) {
            if ($request->has($field) || $creating) {
                $shift->{$field} = $request->has($field) ? $request->boolean($field) : $default;
            }
        }

        $numberDefaults = [
            'grace_period_minutes' => 15,
            'late_after_minutes' => 15,
            'minimum_working_minutes' => 480,
            'late_threshold_minutes' => 30,
            'half_day_after_minutes' => 240,
            'overtime_starts_after_minutes' => 480,
            'minimum_overtime_minutes' => 30,
        ];
        foreach ($numberDefaults as $field => $default) {
            if ($request->has($field) || $creating) {
                $shift->{$field} = (int) $request->input($field, $default);
            }
        }

        if ($request->has('overtime_calculation') || $creating) {
            $shift->overtime_calculation = $request->input('overtime_calculation', 'Hourly');
        }

        if ($request->has('breaks')) {
            $breaks = collect($request->input('breaks'))->map(function (array $break) {
                $start = $this->normalTime($break['start_time']);
                $end = $this->normalTime($break['end_time']);
                $break['start_time'] = $start;
                $break['end_time'] = $end;
                $break['duration_minutes'] = $break['duration_minutes'] ?? $this->minutesBetween($start, $end);

                return $break;
            })->values()->all();
            $shift->breaks = json_encode($breaks);
            $shift->break_duration = $this->minutesAsDuration(collect($breaks)->sum('duration_minutes'));
        } elseif ($request->has('break_duration')) {
            $shift->break_duration = $request->input('break_duration');
        } elseif ($creating) {
            $shift->breaks = json_encode([]);
            $shift->break_duration = '00:00';
        }

        if ($request->has('working_days')) {
            $days = collect($request->input('working_days'))->map(function (array $day) use ($shift) {
                $day['start_time'] = isset($day['start_time']) && $day['start_time'] !== null
                    ? $this->normalTime($day['start_time']) : $shift->start_time;
                $day['end_time'] = isset($day['end_time']) && $day['end_time'] !== null
                    ? $this->normalTime($day['end_time']) : $shift->end_time;

                return $day;
            })->values()->all();
            $shift->working_days = json_encode($days);
        } elseif ($creating) {
            $shift->working_days = json_encode($this->defaultWorkingDays($shift->start_time, $shift->end_time));
        }

        $shift->grace_time_late = $this->minutesAsDuration((int) ($shift->grace_period_minutes ?? 15));
        $shift->overtime_after = $this->minutesAsDuration((int) ($shift->overtime_starts_after_minutes ?? 480));
        $shift->total_duration = $this->totalDuration($shift);
    }

    private function syncEmployees(Shift $shift, array $employeeIds): void
    {
        User::where('role', 'employee')->where('assigned_shift_id', $shift->id)
            ->whereNotIn('id', $employeeIds)->update(['assigned_shift_id' => null]);
        if ($employeeIds !== []) {
            User::where('role', 'employee')->whereIn('id', $employeeIds)
                ->update(['assigned_shift_id' => $shift->id]);
        }
    }

    private function data(Shift $shift): array
    {
        $data = $shift->toArray();
        $data['cross_midnight'] = (bool) $shift->cross_midnight;
        $data['breaks_enabled'] = (bool) $shift->breaks_enabled;
        $data['breaks'] = $this->jsonArray($shift->breaks);
        $data['early_leaving_allowed'] = (bool) $shift->early_leaving_allowed;
        $data['auto_mark_late'] = (bool) $shift->auto_mark_late;
        $data['auto_mark_half_day'] = (bool) $shift->auto_mark_half_day;
        $data['overtime_enabled'] = (bool) $shift->overtime_enabled;
        $data['overtime_approval_required'] = (bool) $shift->overtime_approval_required;
        $data['working_days'] = $this->jsonArray($shift->working_days);
        $data['gross_duration'] = $this->minutesAsReadable(
            $this->minutesBetween($shift->start_time, $shift->end_time)
        );
        $data['net_working_duration'] = $shift->total_duration;
        $employees = User::where('role', 'employee')->where('assigned_shift_id', $shift->id)
            ->get(['id', 'employee_id', 'name', 'email', 'department', 'designation', 'avatar', 'status']);
        $data['assigned_employees_count'] = $employees->count();
        $data['assigned_employees'] = $employees;

        return $data;
    }

    private function normalTime(string $value): string
    {
        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Invalid time value: {$value}");
        }
    }

    private function totalDuration(Shift $shift): string
    {
        $minutes = $this->minutesBetween($shift->start_time, $shift->end_time);
        $breakMinutes = $shift->breaks_enabled ? $this->durationMinutes((string) $shift->break_duration) : 0;

        return $this->minutesAsReadable(max(0, $minutes - $breakMinutes));
    }

    private function minutesBetween(string $start, string $end): int
    {
        $startTime = Carbon::parse($start);
        $endTime = Carbon::parse($end);
        if ($endTime->lessThanOrEqualTo($startTime)) {
            $endTime->addDay();
        }

        return (int) $startTime->diffInMinutes($endTime);
    }

    private function durationMinutes(string $duration): int
    {
        if (str_contains($duration, ':')) {
            [$hours, $minutes] = array_pad(explode(':', $duration, 2), 2, 0);
            return ((int) $hours * 60) + (int) $minutes;
        }

        return (int) $duration;
    }

    private function minutesAsDuration(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function minutesAsReadable(int $minutes): string
    {
        return intdiv($minutes, 60) . 'h ' . sprintf('%02dm', $minutes % 60);
    }

    private function jsonArray(mixed $value): array
    {
        return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
    }

    private function defaultWorkingDays(string $start, string $end): array
    {
        return collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
            ->map(fn (string $day) => [
                'day' => $day,
                'enabled' => ! in_array($day, ['Saturday', 'Sunday']),
                'start_time' => $start,
                'end_time' => $end,
            ])->all();
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
