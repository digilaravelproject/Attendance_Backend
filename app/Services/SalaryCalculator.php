<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;

class SalaryCalculator
{
    public function preview(User $employee, Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $workingDates = collect();
        for ($date = $month->copy(); $date->lte($end); $date->addDay()) {
            if (! $date->isWeekend()) {
                $workingDates->push($date->toDateString());
            }
        }

        $holidayDates = Holiday::whereBetween('date', [$month, $end])->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());
        $workingDates = $workingDates->diff($holidayDates)->values();
        $records = Attendance::where('user_id', $employee->id)
            ->whereBetween('attendance_date', [$month, $end])->get();
        $leaves = LeaveRequest::with('leaveType')
            ->where('user_id', $employee->id)->where('status', 'Approved')
            ->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $month)->get();

        $paidLeaveDates = collect();
        $unpaidLeaveDates = collect();
        foreach ($leaves as $leave) {
            $from = $leave->from_date->greaterThan($month) ? $leave->from_date->copy() : $month->copy();
            $to = $leave->to_date->lessThan($end) ? $leave->to_date->copy() : $end->copy();
            for ($date = $from; $date->lte($to); $date->addDay()) {
                if (! $workingDates->contains($date->toDateString())) {
                    continue;
                }
                $days = $leave->session === 'Full Day' ? 1 : 0.5;
                ($leave->leaveType?->is_paid ? $paidLeaveDates : $unpaidLeaveDates)->put($date->toDateString(), $days);
            }
        }

        $presentDays = $records->whereNotIn('status', ['Half Day', 'Absent'])->count();
        $halfDays = $records->where('status', 'Half Day')->count();
        $lateDays = $records->where('status', 'Late')->count();
        $overtimeMinutes = (int) $records->sum('overtime_minutes');
        $paidLeaves = (float) $paidLeaveDates->sum();
        $unpaidLeaves = (float) $unpaidLeaveDates->sum();
        $coveredDates = $records->pluck('attendance_date')->map(fn ($date) => $date->toDateString())
            ->merge($paidLeaveDates->keys())->merge($unpaidLeaveDates->keys())->unique();
        $pastWorkingDates = $workingDates->filter(fn ($date) => Carbon::parse($date)->lte(now()->endOfDay()));
        $absentDays = max(0, $pastWorkingDates->diff($coveredDates)->count());

        $gross = round((float) ($employee->monthly_salary ?? 0), 2);
        $earnings = [
            ['name' => 'Basic', 'amount' => round($gross * 0.50, 2)],
            ['name' => 'HRA', 'amount' => round($gross * 0.25, 2)],
            ['name' => 'Allowances', 'amount' => round($gross * 0.25, 2)],
        ];
        $deductibleDays = $absentDays + $unpaidLeaves + ($halfDays * 0.5);
        $absenceDeduction = $workingDates->count() > 0
            ? round(($gross / $workingDates->count()) * $deductibleDays, 2)
            : 0;
        $deductions = $absenceDeduction > 0
            ? [['name' => 'Attendance / LWP', 'amount' => $absenceDeduction]]
            : [];

        return [
            'attendance_summary' => [
                'total_working_days' => $workingDates->count(),
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'paid_leaves' => $paidLeaves,
                'unpaid_leaves' => $unpaidLeaves,
                'half_days' => $halfDays,
                'late_coming_days' => $lateDays,
                'overtime_minutes' => $overtimeMinutes,
                'overtime_hours' => round($overtimeMinutes / 60, 2),
            ],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross_earnings' => $gross,
            'total_deductions' => $absenceDeduction,
            'net_payable' => max(0, round($gross - $absenceDeduction, 2)),
        ];
    }
}
