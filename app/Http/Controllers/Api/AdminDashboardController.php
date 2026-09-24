<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        if (strtolower((string) $request->user()->role) === 'employee') {
            return app(EmployeeAttendanceController::class)->dashboard($request);
        }

        $today = now();
        $date = $today->toDateString();
        $employeeIds = User::where('role', 'employee')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'Inactive'))
            ->pluck('id');
        $employees = $employeeIds->count();
        $presentIds = Attendance::whereDate('attendance_date', $date)
            ->whereIn('user_id', $employeeIds)
            ->whereNotIn('status', ['Absent', 'Leave'])->distinct()->pluck('user_id');
        $present = $presentIds->count();
        $onLeave = LeaveRequest::where('status', 'Approved')
            ->whereIn('user_id', $employeeIds)->whereNotIn('user_id', $presentIds)
            ->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)
            ->distinct('user_id')->count('user_id');
        $absent = max(0, $employees - $present - $onLeave);

        return response()->json([
            'status' => true,
            'message' => 'Admin dashboard retrieved successfully.',
            'data' => [
                'greeting' => $this->greeting($today).', '.$request->user()->name,
                'company_name' => $request->user()->company_name,
                'date' => $date,
                'day' => $today->format('l'),
                'unread_notifications' => Notification::where('recipient_id', $request->user()->id)->whereNull('read_at')->count(),
                'todays_summary' => [
                    'total_employees' => $employees,
                    'present' => $present,
                    'absent' => $absent,
                    'on_leave' => $onLeave,
                    'present_percentage' => $this->percentage($present, $employees),
                    'absent_percentage' => $this->percentage($absent, $employees),
                    'on_leave_percentage' => $this->percentage($onLeave, $employees),
                ],
                'current_shift' => $this->currentShift($today),
                'recent_activities' => $this->recentActivities($request->user()->id),
            ],
        ]);
    }

    public function moduleStatistics(): JsonResponse
    {
        $date = now()->toDateString();
        $definitions = [
            ['name' => 'Departments', 'slug' => 'departments', 'table' => 'departments'],
            ['name' => 'Roles', 'slug' => 'roles', 'table' => 'roles'],
            ['name' => 'Designations', 'slug' => 'designations', 'table' => 'designations'],
            ['name' => 'Shifts', 'slug' => 'shifts', 'table' => 'shifts'],
            ['name' => 'Employees', 'slug' => 'employees', 'table' => 'users', 'employee' => true],
            ['name' => 'Projects', 'slug' => 'projects', 'table' => 'projects'],
            ['name' => 'Tasks', 'slug' => 'tasks', 'table' => 'tasks'],
            ['name' => 'Assets', 'slug' => 'assets', 'table' => 'assets'],
            ['name' => 'Companies', 'slug' => 'companies', 'table' => 'companies'],
            ['name' => 'Payroll', 'slug' => 'payroll', 'table' => 'payrolls'],
            ['name' => 'Clients', 'slug' => 'clients', 'table' => 'clients'],
            ['name' => 'Leads', 'slug' => 'leads', 'table' => 'leads'],
            ['name' => 'Leave Requests', 'slug' => 'leave_requests', 'table' => 'leave_requests'],
            ['name' => 'Holidays', 'slug' => 'holidays', 'table' => 'holidays'],
        ];

        $modules = collect($definitions)->map(function (array $module) {
            $available = Schema::hasTable($module['table']);
            $query = $available ? DB::table($module['table']) : null;
            if ($query && ($module['employee'] ?? false)) {
                $query->where('role', 'employee');
            }

            return [
                'name' => $module['name'],
                'slug' => $module['slug'],
                'count' => $query?->count() ?? 0,
                'available' => $available,
            ];
        });

        $pendingTasks = 0;
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'status')) {
            $pendingTasks = DB::table('tasks')->whereIn('status', ['Pending', 'pending', 'Open', 'open'])->count();
        }

        return response()->json([
            'status' => true,
            'message' => 'Module statistics retrieved successfully.',
            'data' => [
                'employees' => User::where('role', 'employee')->count(),
                'present_today' => Attendance::whereDate('attendance_date', $date)
                    ->whereNotIn('status', ['Absent', 'Leave'])->distinct('user_id')->count('user_id'),
                'pending_tasks' => $pendingTasks,
                'modules' => $modules,
            ],
        ]);
    }

    private function currentShift(Carbon $today): ?array
    {
        $shifts = Shift::where('status', 'Active')->orderBy('start_time')->get();
        $shift = $shifts->first(fn (Shift $item) => $this->isOngoing($item, $today)) ?? $shifts->first();
        if (! $shift) {
            return null;
        }

        $grace = (int) ($shift->grace_period_minutes ?? 0);
        $start = Carbon::parse($today->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($today->toDateString().' '.$shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'code' => $shift->code,
            'status' => $this->isOngoing($shift, $today) ? 'Ongoing' : 'Scheduled',
            'start_time' => $start->format('h:i A'),
            'end_time' => $end->format('h:i A'),
            'check_in_window' => [
                'from' => $start->copy()->subMinutes($grace)->format('h:i A'),
                'to' => $start->copy()->addMinutes($grace)->format('h:i A'),
            ],
            'check_out_window' => [
                'from' => $end->copy()->subMinutes($grace)->format('h:i A'),
                'to' => $end->copy()->addMinutes($grace)->format('h:i A'),
            ],
        ];
    }

    private function recentActivities(int $adminId): array
    {
        $notifications = Notification::where('recipient_id', $adminId)->latest()->limit(10)->get()
            ->map(fn (Notification $item) => [
                'type' => 'admin_action', 'title' => $item->title, 'description' => $item->message,
                'status' => ucfirst((string) $item->action), 'occurred_at' => $item->created_at,
                'entity_id' => $item->entity_id,
            ]);
        $attendance = Attendance::with(['employee:id,name', 'shift:id,name'])->latest('check_in_at')->limit(10)->get()
            ->map(fn (Attendance $item) => [
                'type' => 'attendance', 'title' => ($item->employee?->name ?? 'Employee').' checked in',
                'description' => $item->shift?->name ?? $item->status, 'status' => $item->status,
                'occurred_at' => $item->check_in_at ?? $item->created_at, 'entity_id' => $item->id,
            ]);
        $leaves = LeaveRequest::with(['employee:id,name', 'leaveType:id,name'])->latest()->limit(10)->get()
            ->map(fn (LeaveRequest $item) => [
                'type' => 'leave_request', 'title' => ($item->employee?->name ?? 'Employee').' applied for leave',
                'description' => $item->from_date?->format('d M Y').' · '.($item->leaveType?->name ?? 'Leave'),
                'status' => $item->status, 'occurred_at' => $item->created_at, 'entity_id' => $item->id,
            ]);

        return $notifications->concat($attendance)->concat($leaves)
            ->sortByDesc('occurred_at')->take(10)->values()
            ->map(function (array $item) {
                $time = $item['occurred_at'];
                $item['occurred_at'] = $time?->toIso8601String();
                $item['time_ago'] = $time?->diffForHumans();

                return $item;
            })->all();
    }

    private function isOngoing(Shift $shift, Carbon $now): bool
    {
        $start = Carbon::parse($now->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($now->toDateString().' '.$shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $now->betweenIncluded($start, $end);
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }

    private function greeting(Carbon $time): string
    {
        return match (true) {
            $time->hour < 12 => 'Good Morning',
            $time->hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };
    }
}
