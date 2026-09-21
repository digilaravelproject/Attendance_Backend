<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAction;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeaveManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'string', Rule::in(['all', 'All', 'pending', 'Pending', 'approved', 'Approved', 'rejected', 'Rejected', 'cancelled', 'Cancelled'])],
            'date_range' => ['sometimes', Rule::in(['all_time', 'today', 'this_week', 'this_month', 'last_month', 'custom'])],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'designation_id' => ['sometimes', 'integer', 'exists:designations,id'],
            'leave_type_id' => ['sometimes', 'integer', 'exists:leave_types,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $query = LeaveRequest::query()->with(['employee.departmentDetails', 'employee.designationDetails', 'leaveType', 'assignee:id,name,email,role,designation']);
        if ($request->user()->role === 'employee') {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('year')) {
            $query->whereYear('from_date', (int) $request->input('year'));
        }
        $this->applyFilters($query, $request);
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginator = $query->latest()->paginate($perPage);
        $countQuery = LeaveRequest::query();
        if ($request->user()->role === 'employee') {
            $countQuery->where('user_id', $request->user()->id);
        }
        if ($request->filled('year')) {
            $countQuery->whereYear('from_date', (int) $request->input('year'));
        }
        $counts = $countQuery->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'status' => true,
            'message' => 'Leave requests retrieved successfully.',
            'counts' => [
                'all' => array_sum($counts->all()),
                'pending' => (int) ($counts['Pending'] ?? 0),
                'approved' => (int) ($counts['Approved'] ?? 0),
                'rejected' => (int) ($counts['Rejected'] ?? 0),
                'cancelled' => (int) ($counts['Cancelled'] ?? 0),
            ],
            'data' => collect($paginator->items())->map(fn (LeaveRequest $leave) => $this->data($leave)),
            'leave_balances' => $request->user()->role === 'employee' ? $this->leaveBalances($request->user(), (int) $request->input('year', now()->year)) : null,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role === 'employee') {
            $request->merge(['user_id' => $request->user()->id]);
        }
        $validator = Validator::make($request->all(), $this->requestRules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        if ($request->user()->role === 'employee' && ! $request->filled('assigned_to_user_id')) {
            return $this->validationError(['assigned_to_user_id' => ['The assigned to field is required.']]);
        }
        if ($request->input('session', 'Full Day') !== 'Full Day' && $request->input('from_date') !== $request->input('to_date')) {
            return $this->validationError(['session' => ['A half-day session must have the same from and to date.']]);
        }

        if ($request->filled('assigned_to_user_id')) {
            $assignee = User::find($request->input('assigned_to_user_id'));
            if (! $assignee || ! $this->isApprover($assignee) || $assignee->id === $request->user()->id) {
                return $this->validationError(['assigned_to_user_id' => ['Select an active administrator or manager other than yourself.']]);
            }
        }
        $payload = $this->requestPayload($request);
        if ($request->user()->role === 'employee') {
            $payload['user_id'] = $request->user()->id;
            $payload['total_days'] = $this->totalDays($request);
            unset($payload['attachment_name'], $payload['attachment_path']);
        }
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $payload['attachment_name'] = $file->getClientOriginalName();
            $payload['attachment_path'] = $file->store('leave-attachments/'.$payload['user_id'], 'public');
        }

        $leave = LeaveRequest::create($payload);
        LeaveRequestAction::create([
            'leave_request_id' => $leave->id,
            'action' => 'Submitted',
            'actor_user_id' => $this->adminUserId($request),
            'note' => $request->user()->role === 'employee' ? 'Leave request submitted by employee.' : 'Leave request created by an administrator.',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave request created successfully.',
            'data' => $this->load($leave),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (! $leave) {
            return response()->json(['status' => false, 'message' => 'Leave request not found.'], 404);
        }
        if (request()->user()->role === 'employee' && (int) $leave->user_id !== (int) request()->user()->id && (int) $leave->assigned_to_user_id !== (int) request()->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave request details retrieved successfully.',
            'data' => $this->load($leave),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (! $leave) {
            return response()->json(['status' => false, 'message' => 'Leave request not found.'], 404);
        }
        if ($leave->status !== 'Pending') {
            return response()->json(['status' => false, 'message' => 'Only pending leave requests can be edited.'], 422);
        }

        $validator = Validator::make($request->all(), $this->requestRules(true));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $payload = $this->requestPayload($request, true);
        if ($payload !== []) {
            $leave->update($payload);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave request updated successfully.',
            'data' => $this->load($leave->fresh()),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        return $this->review($request, $id, 'Approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        return $this->review($request, $id, 'Rejected');
    }

    public function destroy(string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (! $leave) {
            return response()->json(['status' => false, 'message' => 'Leave request not found.'], 404);
        }
        $leave->delete();

        return response()->json(['status' => true, 'message' => 'Leave request deleted successfully.']);
    }

    public function calendar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => ['sometimes', 'date_format:Y-m'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $start = $request->filled('year') ? Carbon::create((int) $request->input('year'), 1, 1)->startOfDay() : $month;
        $end = $request->filled('year') ? $start->copy()->endOfYear() : $month->copy()->endOfMonth();
        $query = LeaveRequest::with(['employee.departmentDetails', 'leaveType'])
            ->where('status', 'Approved')
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $start);
        if ($request->user()->role === 'employee') {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $request->input('department_id')));
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave calendar retrieved successfully.',
            'month' => $request->filled('year') ? null : $month->format('Y-m'),
            'year' => (int) $start->year,
            'data' => $query->orderBy('from_date')->get()->map(fn (LeaveRequest $leave) => $this->data($leave)),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $from = Carbon::parse($request->input('from_date', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to_date', now()->endOfYear()->toDateString()))->endOfDay();
        if ($to->lt($from)) {
            return $this->validationError(['to_date' => ['The to date must be after or equal to the from date.']]);
        }
        $base = LeaveRequest::whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from);
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as requests, SUM(total_days) as days')
            ->groupBy('status')->get();
        $byType = (clone $base)->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->selectRaw('leave_types.id, leave_types.name, COUNT(*) as requests, SUM(leave_requests.total_days) as days')
            ->groupBy('leave_types.id', 'leave_types.name')->get();
        $byDepartment = (clone $base)->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->selectRaw("COALESCE(departments.name, users.department, 'Unassigned') as department, COUNT(*) as requests, SUM(leave_requests.total_days) as days")
            ->groupBy('departments.name', 'users.department')->get();

        return response()->json([
            'status' => true,
            'message' => 'Leave report retrieved successfully.',
            'period' => ['from_date' => $from->toDateString(), 'to_date' => $to->toDateString()],
            'summary' => [
                'total_requests' => (clone $base)->count(),
                'total_days' => (float) (clone $base)->sum('total_days'),
                'by_status' => $byStatus,
                'by_leave_type' => $byType,
                'by_department' => $byDepartment,
            ],
        ]);
    }

    public function leaveTypes(Request $request): JsonResponse
    {
        $query = LeaveType::query()->withCount('requests');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave types retrieved successfully.',
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function approvers(Request $request): JsonResponse
    {
        $items = User::query()->where('id', '!=', $request->user()->id)
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhereHas('designationDetails', fn ($designation) => $designation->where('hierarchy_level', 'manager'))
                    ->orWhereHas('roles', fn ($role) => $role->where('name', 'like', '%manager%'));
            })
            ->where('status', '!=', 'Inactive')
            ->orderBy('name')->get(['id', 'name', 'email', 'role', 'designation', 'employee_id', 'avatar']);

        return response()->json(['status' => true, 'message' => 'Leave approvers retrieved successfully.', 'total' => $items->count(), 'data' => $items]);
    }

    public function holidays(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['year' => ['sometimes', 'integer', 'between:2000,2100']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $year = (int) $request->input('year', now()->year);
        $items = Holiday::whereYear('date', $year)->orderBy('date')->get()->map(fn (Holiday $holiday) => [
            'id' => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'day' => $holiday->date->format('D'),
            'type' => $holiday->type,
            'description' => $holiday->description,
        ]);
        $counts = $items->countBy('type');

        return response()->json([
            'status' => true,
            'message' => 'Holidays retrieved successfully.',
            'year' => $year,
            'summary' => [
                'total' => $items->count(),
                'national' => $counts['National'] ?? 0,
                'restricted' => $counts['Restricted'] ?? 0,
                'optional' => $counts['Optional'] ?? 0,
            ],
            'months' => $items->groupBy(fn ($item) => Carbon::parse($item['date'])->format('F'))
                ->map(fn ($holidays, $month) => ['month' => $month, 'count' => $holidays->count(), 'holidays' => $holidays->values()])
                ->values(),
            'data' => $items,
        ]);
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['National', 'Restricted', 'Optional'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $holiday = Holiday::create($validator->validated());

        return response()->json(['status' => true, 'message' => 'Holiday created successfully.', 'data' => $holiday], 201);
    }

    public function storeLeaveType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->leaveTypeRules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $payload = $validator->validated();
        $payload['code'] = strtoupper($payload['code'] ?? Str::slug($payload['name'], '_'));
        $type = LeaveType::create($payload);

        return response()->json(['status' => true, 'message' => 'Leave type created successfully.', 'data' => $type], 201);
    }

    public function showLeaveType(string $id): JsonResponse
    {
        $type = LeaveType::withCount('requests')->find($id);
        if (! $type) {
            return response()->json(['status' => false, 'message' => 'Leave type not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave type details retrieved successfully.',
            'data' => $type,
        ]);
    }

    public function updateLeaveType(Request $request, string $id): JsonResponse
    {
        $type = LeaveType::find($id);
        if (! $type) {
            return response()->json(['status' => false, 'message' => 'Leave type not found.'], 404);
        }
        $validator = Validator::make($request->all(), $this->leaveTypeRules($type));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $payload = $validator->validated();
        if (isset($payload['code'])) {
            $payload['code'] = strtoupper($payload['code']);
        }
        $type->update($payload);

        return response()->json(['status' => true, 'message' => 'Leave type updated successfully.', 'data' => $type->fresh()]);
    }

    public function destroyLeaveType(string $id): JsonResponse
    {
        $type = LeaveType::withCount('requests')->find($id);
        if (! $type) {
            return response()->json(['status' => false, 'message' => 'Leave type not found.'], 404);
        }
        if ($type->requests_count > 0) {
            return response()->json(['status' => false, 'message' => 'A leave type with requests cannot be deleted.'], 422);
        }
        $type->delete();

        return response()->json(['status' => true, 'message' => 'Leave type deleted successfully.']);
    }

    private function review(Request $request, string $id, string $status): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (! $leave) {
            return response()->json(['status' => false, 'message' => 'Leave request not found.'], 404);
        }
        if ($request->user()->role !== 'admin' && ((int) $leave->assigned_to_user_id !== (int) $request->user()->id || (int) $leave->user_id === (int) $request->user()->id || ! $this->isApprover($request->user()))) {
            return response()->json(['status' => false, 'message' => 'Only the assigned manager or an administrator may review this leave.'], 403);
        }
        if ($leave->status !== 'Pending') {
            return response()->json(['status' => false, 'message' => 'Only pending leave requests can be reviewed.'], 422);
        }
        $validator = Validator::make($request->all(), [
            'note' => [$status === 'Rejected' ? 'required' : 'sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        DB::transaction(function () use ($request, $leave, $status) {
            $leave->update([
                'status' => $status,
                'review_note' => $request->input('note'),
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            LeaveRequestAction::create([
                'leave_request_id' => $leave->id,
                'action' => $status,
                'actor_user_id' => $request->user()->id,
                'note' => $request->input('note'),
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => "Leave request {$status} successfully.",
            'data' => $this->load($leave->fresh()),
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($employee) => $employee
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%"))
                    ->orWhereHas('leaveType', fn ($type) => $type->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status') && strtolower($request->input('status')) !== 'all') {
            $query->where('status', ucfirst(strtolower($request->input('status'))));
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->input('leave_type_id'));
        }
        foreach (['department_id', 'designation_id', 'role'] as $field) {
            if ($request->filled($field)) {
                $query->whereHas('employee', fn ($employee) => $employee->where($field, $request->input($field)));
            }
        }
        if ($request->filled('department')) {
            $query->whereHas('employee', fn ($employee) => $employee->where('department', $request->input('department')));
        }
        if ($request->filled('designation')) {
            $query->whereHas('employee', fn ($employee) => $employee->where('designation', $request->input('designation')));
        }

        [$from, $to] = $this->dateRange($request);
        if ($from) {
            $query->whereDate('to_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('from_date', '<=', $to);
        }
    }

    private function dateRange(Request $request): array
    {
        $preset = strtolower((string) $request->input('date_range', ''));

        return match ($preset) {
            'today' => [today(), today()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => [
                $request->filled('from_date') ? Carbon::parse($request->input('from_date')) : null,
                $request->filled('to_date') ? Carbon::parse($request->input('to_date')) : null,
            ],
        };
    }

    private function requestRules(bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'user_id' => [$presence, 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'employee'))],
            'leave_type_id' => [$presence, 'integer', 'exists:leave_types,id'],
            'from_date' => [$presence, 'date_format:Y-m-d'],
            'to_date' => [$presence, 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'total_days' => ['sometimes', 'numeric', 'min:0.5'],
            'session' => ['sometimes', Rule::in(['Full Day', '1st Half', '2nd Half'])],
            'reason' => [$presence, 'string', 'max:3000'],
            'contact_during_leave' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address_during_leave' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assigned_to_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'attachment' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'attachment_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'attachment_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    private function requestPayload(Request $request, bool $updating = false): array
    {
        $payload = $request->only([
            'user_id', 'leave_type_id', 'from_date', 'to_date', 'total_days', 'reason',
            'contact_during_leave', 'address_during_leave', 'assigned_to_user_id',
            'session', 'attachment_name', 'attachment_path',
        ]);
        if (! $request->has('total_days') && $request->filled('from_date') && $request->filled('to_date')) {
            $payload['total_days'] = Carbon::parse($request->input('from_date'))
                ->diffInDays(Carbon::parse($request->input('to_date'))) + 1;
        }
        if (! $updating) {
            $payload['status'] = 'Pending';
        }

        return $payload;
    }

    private function leaveTypeRules(?LeaveType $type = null): array
    {
        $presence = $type ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255', Rule::unique('leave_types', 'name')->ignore($type?->id)],
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('leave_types', 'code')->ignore($type?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'annual_allowance' => ['sometimes', 'integer', 'min:0', 'max:366'],
            'is_paid' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ];
    }

    private function load(LeaveRequest $leave): array
    {
        return $this->data($leave->load([
            'employee.departmentDetails', 'employee.designationDetails', 'leaveType',
            'assignee:id,name,email,role,designation',
            'reviewer:id,name,email', 'actions.actor:id,name,email',
        ]));
    }

    private function data(LeaveRequest $leave): array
    {
        $data = $leave->toArray();
        if ($leave->attachment_path) {
            $data['attachment_url'] = Storage::disk('public')->url($leave->attachment_path);
        }
        if ($leave->relationLoaded('employee') && $leave->employee && $leave->relationLoaded('leaveType') && $leave->leaveType) {
            $year = Carbon::parse($leave->from_date)->year;
            $taken = LeaveRequest::where('user_id', $leave->user_id)
                ->where('leave_type_id', $leave->leave_type_id)->where('status', 'Approved')
                ->whereYear('from_date', $year)->sum('total_days');
            $pending = LeaveRequest::where('user_id', $leave->user_id)
                ->where('leave_type_id', $leave->leave_type_id)->where('status', 'Pending')
                ->whereYear('from_date', $year)->sum('total_days');
            $data['leave_balance'] = [
                'total' => (float) $leave->leaveType->annual_allowance,
                'taken' => (float) $taken,
                'pending' => (float) $pending,
                'remaining' => max(0, (float) $leave->leaveType->annual_allowance - (float) $taken),
            ];
        }

        return $data;
    }

    private function adminUserId(Request $request): ?int
    {
        return $request->user() instanceof User && strtolower((string) $request->user()->role) === 'admin'
            ? (int) $request->user()->id
            : null;
    }

    private function isApprover(User $user): bool
    {
        return strtolower((string) $user->status) !== 'inactive' && (
            $user->role === 'admin'
            || $user->designationDetails?->hierarchy_level === 'manager'
            || $user->roles()->where('name', 'like', '%manager%')->exists()
        );
    }

    private function totalDays(Request $request): float
    {
        $days = Carbon::parse($request->input('from_date'))->diffInDays(Carbon::parse($request->input('to_date'))) + 1;

        return $request->input('session', 'Full Day') === 'Full Day' ? $days : 0.5;
    }

    private function leaveBalances(User $employee, int $year): array
    {
        return LeaveType::where('status', 'Active')->orderBy('name')->get()->map(function (LeaveType $type) use ($employee, $year) {
            $base = LeaveRequest::where('user_id', $employee->id)->where('leave_type_id', $type->id)
                ->whereYear('from_date', $year);
            $taken = (float) (clone $base)->where('status', 'Approved')->sum('total_days');
            $pending = (float) (clone $base)->where('status', 'Pending')->sum('total_days');

            return [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'total' => (float) $type->annual_allowance,
                'taken' => $taken,
                'pending' => $pending,
                'remaining' => max(0, (float) $type->annual_allowance - $taken),
            ];
        })->all();
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
