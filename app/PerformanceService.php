<?php

namespace App;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Task;
use App\Models\TaskQualityReview;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class PerformanceService
{
    public function period(array $filters): array
    {
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $start = Carbon::parse($filters['from'])->startOfDay();
            $end = Carbon::parse($filters['to'])->endOfDay();
        } else {
            $month = $filters['month'] ?? now()->format('Y-m');
            $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth()->endOfDay();
        }

        return [$start, $end];
    }

    public function summary(User $employee, Carbon $start, Carbon $end): array
    {
        $tasks = $this->taskQuery($employee, $start, $end)->get();
        $projects = $employee->projects()
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();
        $quality = $this->qualitySummary($employee, $start, $end);
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'Completed');
        $timelinessEligible = $completedTasks->filter(fn (Task $task) => $task->due_date && $task->completed_at);
        $onTime = $timelinessEligible->filter(fn (Task $task) => $task->completed_at->lte($task->due_date->copy()->endOfDay()))->count();

        $metrics = [
            'project_progress' => ['value' => $projects->isNotEmpty() ? round((float) $projects->avg('progress'), 2) : null, 'weight' => 25],
            'task_completion' => ['value' => $totalTasks > 0 ? round($completedTasks->count() * 100 / $totalTasks, 2) : null, 'weight' => 45],
            'timely_submission' => ['value' => $timelinessEligible->isNotEmpty() ? round($onTime * 100 / $timelinessEligible->count(), 2) : null, 'weight' => 20],
            'quality_of_work' => ['value' => $quality['overall_percent'], 'weight' => 10],
        ];
        $score = $this->weightedScore($metrics);

        return [
            'employee' => $this->employeeData($employee),
            'period' => $this->periodData($start, $end),
            'score' => $score,
            'evaluation' => $this->evaluation($score),
            'source_counts' => ['projects' => $projects->count(), 'tasks' => $totalTasks, 'quality_reviews' => $quality['review_count']],
            'metrics' => [
                'project_progress' => ['average_percent' => $metrics['project_progress']['value'], 'total_projects' => $projects->count()],
                'task_completion' => ['percent' => $metrics['task_completion']['value'], 'completed' => $completedTasks->count(), 'total' => $totalTasks],
                'timely_submission' => ['percent' => $metrics['timely_submission']['value'], 'on_time' => $onTime, 'eligible_completed_tasks' => $timelinessEligible->count()],
                'quality_of_work' => $quality,
            ],
        ];
    }

    public function attendance(User $employee, Carbon $start, Carbon $end): array
    {
        $records = $employee->attendances()
            ->with('shift:id,name,start_time,end_time')
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('attendance_date')
            ->get();
        $byDate = $records->keyBy(fn ($record) => $record->attendance_date->toDateString());
        $approvedLeaves = $employee->leaveRequests()->where('status', 'Approved')
            ->whereDate('from_date', '<=', $end->toDateString())
            ->whereDate('to_date', '>=', $start->toDateString())->get();
        $holidays = Holiday::query()->get();

        $calendar = collect(CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($byDate, $approvedLeaves, $holidays) {
                $dateString = $date->toDateString();
                $record = $byDate->get($dateString);
                $holiday = $holidays->first(fn (Holiday $item) => $this->isHolidayOn($item, $date));
                $leave = $approvedLeaves->first(fn (LeaveRequest $item) => $date->betweenIncluded($item->from_date, $item->to_date));
                $status = $record?->status;
                if (! $status) {
                    $status = $holiday ? 'Holiday' : ($date->isWeekend() ? 'Weekend' : ($leave ? 'Leave' : 'Not Recorded'));
                }

                return [
                    'date' => $dateString,
                    'day' => $date->format('D'),
                    'status' => $status,
                    'holiday' => $holiday?->name,
                    'attendance_id' => $record?->id,
                    'check_in_at' => $record?->check_in_at,
                    'check_out_at' => $record?->check_out_at,
                    'working_minutes' => $record?->working_minutes,
                ];
            });

        $workingDays = $calendar->whereNotIn('status', ['Weekend', 'Holiday'])->count();
        $presentEquivalent = $records->sum(function ($record) {
            $status = strtolower((string) $record->status);

            return str_contains($status, 'half') ? 0.5 : (str_contains($status, 'present') ? 1 : 0);
        });

        return [
            'employee' => $this->employeeData($employee),
            'period' => $this->periodData($start, $end),
            'summary' => [
                'working_days' => $workingDays,
                'present' => $records->filter(fn ($record) => str_contains(strtolower((string) $record->status), 'present'))->count(),
                'half_day' => $records->filter(fn ($record) => str_contains(strtolower((string) $record->status), 'half'))->count(),
                'absent' => $records->filter(fn ($record) => str_contains(strtolower((string) $record->status), 'absent'))->count(),
                'leave' => $calendar->where('status', 'Leave')->count(),
                'attendance_percent' => $workingDays > 0 ? round($presentEquivalent * 100 / $workingDays, 2) : null,
            ],
            'calendar' => $calendar->values(),
            'recent_records' => $records->sortByDesc('attendance_date')->take(10)->values(),
        ];
    }

    public function leave(User $employee, Carbon $start, Carbon $end): array
    {
        $requests = $employee->leaveRequests()
            ->with(['leaveType:id,name,code,annual_allowance', 'reviewer:id,name,designation'])
            ->whereDate('from_date', '<=', $end->toDateString())
            ->whereDate('to_date', '>=', $start->toDateString())
            ->orderByDesc('from_date')->get();
        $approved = $requests->where('status', 'Approved');
        $year = (int) $start->year;
        $yearApproved = $employee->leaveRequests()->where('status', 'Approved')
            ->whereDate('from_date', '<=', Carbon::create($year, 12, 31)->toDateString())
            ->whereDate('to_date', '>=', Carbon::create($year, 1, 1)->toDateString())->get();
        $types = LeaveType::query()->where('status', 'Active')->orderBy('name')->get();

        $balances = $types->map(function (LeaveType $type) use ($yearApproved, $year) {
            $taken = $yearApproved->where('leave_type_id', $type->id)
                ->sum(fn (LeaveRequest $leave) => $this->overlapDays($leave, Carbon::create($year, 1, 1), Carbon::create($year, 12, 31)));

            return [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'annual_allowance' => $type->annual_allowance,
                'taken' => round($taken, 2),
                'balance' => round(max(0, $type->annual_allowance - $taken), 2),
            ];
        });

        return [
            'employee' => $this->employeeData($employee),
            'period' => $this->periodData($start, $end),
            'summary' => [
                'approved_days_in_period' => round($approved->sum(fn (LeaveRequest $leave) => $this->overlapDays($leave, $start, $end)), 2),
                'pending_requests' => $requests->where('status', 'Pending')->count(),
                'annual_allowance' => $balances->sum('annual_allowance'),
                'annual_taken' => round($balances->sum('taken'), 2),
                'annual_balance' => round($balances->sum('balance'), 2),
            ],
            'balances' => $balances->values(),
            'requests' => $requests->values(),
        ];
    }

    public function tasks(User $employee, Carbon $start, Carbon $end): array
    {
        $tasks = $this->taskQuery($employee, $start, $end)
            ->with(['project:id,name', 'creator:id,name,designation', 'subtasks:id,task_id,title,is_completed,completed_at'])
            ->orderByDesc('due_date')->get();
        $completed = $tasks->where('status', 'Completed');
        $eligible = $completed->filter(fn (Task $task) => $task->due_date && $task->completed_at);
        $onTime = $eligible->filter(fn (Task $task) => $task->completed_at->lte($task->due_date->copy()->endOfDay()));

        $items = $tasks->map(function (Task $task) {
            $isOnTime = $task->status === 'Completed' && $task->due_date && $task->completed_at
                ? $task->completed_at->lte($task->due_date->copy()->endOfDay()) : null;

            return [
                'id' => $task->id,
                'name' => $task->task_name,
                'description' => $task->description,
                'project' => $task->project,
                'priority' => $task->priority,
                'status' => $task->status,
                'assigned_on' => $task->pivot?->created_at ?? $task->created_at,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at,
                'assigned_by' => $task->creator,
                'is_on_time' => $isOnTime,
                'days_from_deadline' => $isOnTime === null ? null : $task->due_date->copy()->startOfDay()->diffInDays($task->completed_at->copy()->startOfDay(), false),
                'subtasks' => [
                    'completed' => $task->subtasks->where('is_completed', true)->count(),
                    'total' => $task->subtasks->count(),
                    'items' => $task->subtasks,
                ],
            ];
        });

        return [
            'employee' => $this->employeeData($employee),
            'period' => $this->periodData($start, $end),
            'summary' => [
                'total' => $tasks->count(),
                'completed' => $completed->count(),
                'in_progress' => $tasks->where('status', 'In Progress')->count(),
                'completion_percent' => $tasks->isNotEmpty() ? round($completed->count() * 100 / $tasks->count(), 2) : null,
                'on_time' => $onTime->count(),
                'timeliness_percent' => $eligible->isNotEmpty() ? round($onTime->count() * 100 / $eligible->count(), 2) : null,
            ],
            'tasks' => $items->values(),
        ];
    }

    public function quality(User $employee, Carbon $start, Carbon $end): array
    {
        $reviews = TaskQualityReview::query()
            ->with(['task:id,task_name,project_id', 'task.project:id,name', 'reviewer:id,name,designation'])
            ->where('employee_id', $employee->id)
            ->whereBetween('reviewed_at', [$start, $end])
            ->orderByDesc('reviewed_at')->get();

        return [
            'employee' => $this->employeeData($employee),
            'period' => $this->periodData($start, $end),
            'summary' => $this->qualitySummaryFrom($reviews),
            'reviews' => $reviews->map(fn (TaskQualityReview $review) => [
                'id' => $review->id,
                'task' => $review->task,
                'reviewer' => $review->reviewer,
                'criteria' => [
                    'deliverable_accuracy' => $review->deliverable_accuracy,
                    'deadline_adherence' => $review->deadline_adherence,
                    'defect_prevention' => $review->defect_prevention,
                    'collaboration' => $review->collaboration,
                ],
                'overall_percent' => round(collect([
                    $review->deliverable_accuracy, $review->deadline_adherence,
                    $review->defect_prevention, $review->collaboration,
                ])->avg(), 2),
                'feedback' => $review->feedback,
                'reviewed_at' => $review->reviewed_at,
            ])->values(),
        ];
    }

    public function employeeData(User $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_id' => $employee->employee_id,
            'name' => $employee->name,
            'email' => $employee->email,
            'avatar' => $employee->avatar,
            'designation' => $employee->designationDetails?->name ?? $employee->designation,
            'department' => $employee->departmentDetails?->name ?? $employee->department,
            'department_id' => $employee->department_id,
            'team' => $employee->team,
        ];
    }

    private function taskQuery(User $employee, Carbon $start, Carbon $end): BelongsToMany
    {
        return $employee->assignedTasks()
            ->where('tasks.status', '!=', 'Cancelled')
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('tasks.created_at', [$start, $end])
                    ->orWhereBetween('tasks.start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('tasks.due_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('tasks.completed_at', [$start, $end])
                    ->orWhere(function (Builder $overlap) use ($start, $end) {
                        $overlap->whereDate('tasks.start_date', '<=', $end->toDateString())
                            ->whereDate('tasks.due_date', '>=', $start->toDateString());
                    });
            });
    }

    private function qualitySummary(User $employee, Carbon $start, Carbon $end): array
    {
        $reviews = TaskQualityReview::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('reviewed_at', [$start, $end])
            ->get();

        return $this->qualitySummaryFrom($reviews);
    }

    private function qualitySummaryFrom(Collection $reviews): array
    {
        $criteria = [
            'deliverable_accuracy' => $reviews->isNotEmpty() ? round((float) $reviews->avg('deliverable_accuracy'), 2) : null,
            'deadline_adherence' => $reviews->isNotEmpty() ? round((float) $reviews->avg('deadline_adherence'), 2) : null,
            'defect_prevention' => $reviews->isNotEmpty() ? round((float) $reviews->avg('defect_prevention'), 2) : null,
            'collaboration' => $reviews->isNotEmpty() ? round((float) $reviews->avg('collaboration'), 2) : null,
        ];

        return [
            'overall_percent' => $reviews->isNotEmpty() ? round((float) collect($criteria)->avg(), 2) : null,
            'review_count' => $reviews->count(),
            'criteria' => $criteria,
        ];
    }

    private function weightedScore(array $metrics): ?float
    {
        $available = collect($metrics)->filter(fn (array $metric) => $metric['value'] !== null);
        $weight = $available->sum('weight');

        return $weight === 0 ? null : round($available->sum(fn (array $metric) => $metric['value'] * $metric['weight']) / $weight, 2);
    }

    private function evaluation(?float $score): string
    {
        if ($score === null) {
            return 'Not Rated';
        }
        if ($score >= 90) {
            return 'Excellent';
        }
        if ($score >= 80) {
            return 'Very Good';
        }
        if ($score >= 70) {
            return 'Good';
        }
        if ($score >= 60) {
            return 'Average';
        }

        return 'Needs Improvement';
    }

    private function periodData(Carbon $start, Carbon $end): array
    {
        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'month' => $start->isSameMonth($end) ? $start->format('Y-m') : null,
            'label' => $start->isSameMonth($end) ? $start->format('F Y') : $start->toDateString().' - '.$end->toDateString(),
        ];
    }

    private function isHolidayOn(Holiday $holiday, Carbon $date): bool
    {
        return $holiday->repeat_every_year
            ? $holiday->date->format('m-d') === $date->format('m-d')
            : $holiday->date->isSameDay($date);
    }

    private function overlapDays(LeaveRequest $leave, Carbon $start, Carbon $end): float
    {
        $overlapStart = $leave->from_date->greaterThan($start) ? $leave->from_date->copy() : $start->copy();
        $overlapEnd = $leave->to_date->lessThan($end) ? $leave->to_date->copy() : $end->copy();
        if ($overlapStart->greaterThan($overlapEnd)) {
            return 0;
        }

        $requestDays = max(1, $leave->from_date->diffInDays($leave->to_date) + 1);
        $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;

        return (float) $leave->total_days * $overlapDays / $requestDays;
    }
}
