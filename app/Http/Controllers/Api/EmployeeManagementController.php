<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmployeeMail;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'employee');
        $search = trim((string) $request->input('search', $request->input('query', '')));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('team', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
            });
        }
        foreach (['department', 'work_mode', 'employee_type', 'employment_status', 'assigned_shift_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $employees = $query->latest()->get($this->columns())->map(fn (User $employee) => $this->data($employee));

        return response()->json([
            'status' => true,
            'message' => 'Employees retrieved successfully.',
            'total' => $employees->count(),
            'data' => $employees,
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
        $this->prepareAliases($request);
        $validator = Validator::make($request->all(), $this->rules($request));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $this->normalizeSalesTarget($data);
        $designation = Designation::findOrFail($data['designation_id']);
        if (! empty($data['department_id'])) {
            $data['department'] = Department::findOrFail($data['department_id'])->name;
        }
        $plainPassword = $this->generatedPassword($data['name']);
        $avatarUrl = $this->storeAvatar($request);

        $employee = new User;
        $employee->forceFill([
            ...Arr::except($data, ['password', 'designation_id', 'avatar', 'monthly_base_salary', 'role_ids']),
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($plainPassword),
            'role' => 'employee',
            'designation_id' => $designation->id,
            'designation' => $designation->name,
            'phone' => $data['mobile_number'],
            'avatar' => $avatarUrl ?? ($data['avatar'] ?? null),
            'status' => $this->accountStatus($data['employment_status'] ?? ($data['status'] ?? 'Active')),
            'employment_status' => $data['employment_status'] ?? ($data['status'] ?? 'Active'),
            'sales_target_enabled' => (bool) ($data['sales_target_enabled'] ?? false),
        ]);
        $employee->save();

        if (array_key_exists('role_ids', $data)) {
            $employee->roles()->sync($data['role_ids']);
        }

        $emailSent = true;
        try {
            Mail::to($employee->email)->send(new WelcomeEmployeeMail($employee->fresh(), $plainPassword));
        } catch (\Throwable $exception) {
            $emailSent = false;
            Log::error('Failed sending welcome email to employee: '.$exception->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => $emailSent
                ? 'Employee created successfully and welcome email sent.'
                : 'Employee created successfully, but the welcome email could not be sent.',
            'email_sent' => $emailSent,
            'data' => $this->data($this->reload($employee)),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = User::where('role', 'employee')->find($id, $this->columns());
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Employee details retrieved successfully.',
            'data' => $this->data($employee),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $employee = User::where('role', 'employee')->find($id);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        $this->prepareAliases($request);
        $validator = Validator::make($request->all(), $this->rules($request, $employee));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $this->normalizeSalesTarget($data);
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        if (isset($data['mobile_number'])) {
            $data['phone'] = $data['mobile_number'];
        }
        if (isset($data['designation_id'])) {
            $data['designation'] = Designation::findOrFail($data['designation_id'])->name;
        }
        if (array_key_exists('department_id', $data)) {
            $data['department'] = $data['department_id']
                ? Department::findOrFail($data['department_id'])->name
                : null;
        }
        if (isset($data['employment_status'])) {
            $data['status'] = $this->accountStatus($data['employment_status']);
        }

        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        $avatarUrl = $this->storeAvatar($request);
        if ($avatarUrl) {
            $data['avatar'] = $avatarUrl;
        }
        unset($data['monthly_base_salary']);

        $employee->forceFill($data)->save();
        if ($roleIds !== null) {
            $employee->roles()->sync($roleIds);
        }

        return response()->json([
            'status' => true,
            'message' => 'Employee updated successfully.',
            'data' => $this->data($this->reload($employee)),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = User::where('role', 'employee')->find($id);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }

        $employee->tokens()->delete();
        $employee->delete();

        return response()->json(['status' => true, 'message' => 'Employee deleted successfully.']);
    }

    private function rules(Request $request, ?User $employee = null): array
    {
        $presence = $employee ? 'sometimes' : 'required';

        return [
            'employee_id' => [$presence, 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($employee?->id)],
            'name' => [$presence, 'string', 'max:255'],
            'gender' => ['sometimes', Rule::in(['Male', 'Female', 'Other'])],
            'date_of_birth' => ['sometimes', 'date_format:Y-m-d', 'before:today'],
            'marital_status' => ['sometimes', Rule::in(['Single', 'Married', 'Divorced', 'Widowed'])],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:10'],
            'mobile_number' => [$presence, 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'alternate_mobile_number' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'email' => [$presence, 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->id)],
            'emergency_contact' => [$presence, 'string', 'regex:/^[0-9+() -]{7,20}$/'],
            'address' => [$presence, 'string', 'max:2000'],
            'street_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'work_mode' => ['sometimes', Rule::in(['Office', 'Remote', 'Hybrid'])],
            'employee_type' => ['sometimes', Rule::in(['Full-time', 'Part-time', 'Contract', 'Freelancer', 'Intern'])],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'designation_id' => [$presence, 'integer', 'exists:designations,id'],
            'team' => ['sometimes', 'nullable', 'string', 'max:255'],
            'assigned_shift_id' => ['sometimes', 'nullable', 'integer', 'exists:shifts,id'],
            'reporting_manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id', Rule::notIn([$employee?->id])],
            'date_of_joining' => [$presence, 'date_format:Y-m-d'],
            'employment_status' => ['sometimes', Rule::in(['Active', 'Probation', 'Notice Period', 'Terminated', 'Inactive'])],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
            'probation_period' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notice_period' => ['sometimes', 'nullable', 'string', 'max:30'],
            'salary_type' => ['sometimes', Rule::in(['Monthly', 'Hourly', 'Weekly', 'Annual CTC'])],
            'monthly_salary' => [$presence, 'numeric', 'min:0', 'max:9999999999.99'],
            'monthly_base_salary' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'sales_target_enabled' => ['sometimes', 'boolean'],
            'sales_target_metric_type' => [Rule::requiredIf(fn () => $request->boolean('sales_target_enabled', (bool) ($employee?->sales_target_enabled ?? false))), 'nullable', Rule::in(['Revenue', 'Deals Closed', 'Units Sold'])],
            'sales_target' => [Rule::requiredIf(fn () => $request->boolean('sales_target_enabled', (bool) ($employee?->sales_target_enabled ?? false))), 'nullable', 'numeric', 'min:0'],
            'sales_target_period' => [Rule::requiredIf(fn () => $request->boolean('sales_target_enabled', (bool) ($employee?->sales_target_enabled ?? false))), 'nullable', Rule::in(['Weekly', 'Monthly', 'Quarterly', 'Yearly'])],
            'incentive_commission_percent' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'account_holder_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ifsc_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'branch_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'skills' => [$presence, 'array', 'min:1'],
            'skills.*' => ['string', 'max:100', 'distinct'],
            'avatar' => $request->hasFile('avatar')
                ? ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']
                : ['sometimes', 'nullable', 'url', 'max:2048'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('status', true)],
        ];
    }

    private function prepareAliases(Request $request): void
    {
        $updates = [];
        if ($request->has('monthly_base_salary') && ! $request->has('monthly_salary')) {
            $updates['monthly_salary'] = $request->input('monthly_base_salary');
        }
        if (! $request->filled('address') && $request->filled('street_address')) {
            $updates['address'] = implode(', ', array_filter([
                $request->input('street_address'), $request->input('city'), $request->input('state'),
                $request->input('postal_code'), $request->input('country'),
            ]));
        }
        if ($request->filled('ifsc_code')) {
            $updates['ifsc_code'] = strtoupper($request->input('ifsc_code'));
        }
        $request->merge($updates);
    }

    private function normalizeSalesTarget(array &$data): void
    {
        if (array_key_exists('sales_target_enabled', $data) && ! (bool) $data['sales_target_enabled']) {
            $data['sales_target_metric_type'] = null;
            $data['sales_target'] = null;
            $data['sales_target_period'] = null;
            $data['incentive_commission_percent'] = null;
        }
    }

    private function storeAvatar(Request $request): ?string
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        $path = $request->file('avatar')->store('employee-avatars', 'public');

        return asset(Storage::url($path));
    }

    private function data(User $employee): array
    {
        $data = $employee->toArray();
        $data['monthly_base_salary'] = $employee->monthly_salary;
        $data['sales_target_enabled'] = (bool) $employee->sales_target_enabled;
        $designation = $employee->designation_id ? Designation::find($employee->designation_id) : null;
        $data['designation_details'] = $designation ? [
            'id' => $designation->id,
            'name' => $designation->name,
            'hierarchy_level' => $designation->hierarchy_level,
            'skills' => is_array($designation->skills)
                ? $designation->skills
                : (json_decode((string) $designation->skills, true) ?: []),
        ] : null;
        $shift = $employee->assigned_shift_id ? Shift::find($employee->assigned_shift_id) : null;
        $data['assigned_shift'] = $shift ? [
            'id' => $shift->id,
            'name' => $shift->name,
            'code' => $shift->code,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
        ] : null;
        $manager = $employee->reporting_manager_id ? User::find($employee->reporting_manager_id) : null;
        $data['reporting_manager'] = $manager?->only(['id', 'employee_id', 'name', 'email', 'designation']);

        return $data;
    }

    private function columns(): array
    {
        return [
            'id', 'designation_id', 'assigned_shift_id', 'reporting_manager_id', 'employee_id',
            'name', 'email', 'role', 'gender', 'date_of_birth', 'marital_status', 'blood_group',
            'mobile_number', 'alternate_mobile_number', 'phone', 'emergency_contact', 'address',
            'street_address', 'city', 'postal_code', 'state', 'country', 'department', 'department_id', 'work_mode',
            'employee_type', 'team', 'designation', 'monthly_salary', 'salary_type',
            'sales_target_enabled', 'sales_target', 'sales_target_metric_type',
            'sales_target_period', 'incentive_commission_percent', 'date_of_joining', 'employment_status',
            'probation_period', 'notice_period', 'skills', 'account_holder_name', 'bank_name',
            'account_number', 'ifsc_code', 'branch_name', 'avatar', 'status', 'created_at', 'updated_at',
        ];
    }

    private function reload(User $employee): User
    {
        return User::query()->select($this->columns())->findOrFail($employee->id);
    }

    private function generatedPassword(string $name): string
    {
        $normalizedName = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name))) ?: 'employee';

        return $normalizedName.'@123';
    }

    private function accountStatus(string $employmentStatus): string
    {
        return in_array($employmentStatus, ['Terminated', 'Inactive']) ? 'Inactive' : 'Active';
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
