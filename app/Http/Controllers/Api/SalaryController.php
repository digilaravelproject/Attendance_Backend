<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salary;
use App\Models\User;
use App\Services\SalaryCalculator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SalaryController extends Controller
{
    public function __construct(private readonly SalaryCalculator $calculator) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => ['sometimes', 'date_format:Y-m'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['all', 'created', 'pending'])],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $month = $this->month($request->input('month'));
        $search = trim((string) $request->input('search', ''));
        $employees = User::where('role', 'employee')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'Inactive'))
            ->when($search !== '', fn ($query) => $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")))
            ->orderBy('name')->get(['id', 'employee_id', 'name', 'avatar', 'department', 'designation', 'monthly_salary']);
        $salaries = Salary::whereDate('salary_month', $month)->get()->keyBy('user_id');
        $rows = $employees->map(function (User $employee) use ($salaries, $month) {
            $salary = $salaries->get($employee->id);

            return [
                'employee' => $this->employeeData($employee),
                'salary_id' => $salary?->id,
                'salary_month' => $month->format('Y-m'),
                // Detailed attendance-based deductions are calculated on the employee
                // preview screen. The listing deliberately stays query-efficient.
                'net_payable' => (float) ($salary?->net_payable ?? $employee->monthly_salary ?? 0),
                'status' => $salary ? 'Created' : 'Pending',
                'payment_status' => $salary?->status,
                'payslip_url' => $salary ? $this->payslipUrl($salary) : null,
            ];
        });
        $summary = ['total_employees' => $rows->count(), 'created' => $rows->where('status', 'Created')->count(), 'pending' => $rows->where('status', 'Pending')->count()];
        if ($request->input('status', 'all') !== 'all') {
            $rows = $rows->where('status', ucfirst($request->input('status')))->values();
        }

        return response()->json(['status' => true, 'message' => 'Employee salary listing retrieved successfully.', 'data' => [
            'month' => $month->format('Y-m'), 'month_label' => $month->format('F Y'), 'summary' => $summary, 'employees' => $rows,
        ]]);
    }

    public function showEmployee(Request $request, string $employeeId): JsonResponse
    {
        $validator = Validator::make($request->all(), ['month' => ['sometimes', 'date_format:Y-m']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $employee = $this->employee($employeeId);
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }
        $month = $this->month($request->input('month'));
        $salary = Salary::where('user_id', $employee->id)->whereDate('salary_month', $month)->first();
        $breakdown = $salary ? $this->salaryAmounts($salary) : $this->calculator->preview($employee, $month);

        return response()->json(['status' => true, 'message' => 'Employee salary details retrieved successfully.', 'data' => [
            'employee' => $this->employeeData($employee),
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'salary_created' => (bool) $salary,
            'salary_id' => $salary?->id,
            ...$breakdown,
            'payment_details' => $salary ? $this->paymentData($salary) : null,
            'payslip_url' => $salary ? $this->payslipUrl($salary) : null,
        ]]);
    }

    public function breakdown(Request $request, string $employeeId): JsonResponse
    {
        return $this->showEmployee($request, $employeeId);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => ['required'],
            'salary_month' => ['required', 'date_format:Y-m'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'payment_mode' => ['required', Rule::in(['Bank Transfer', 'UPI', 'Cash', 'Cheque'])],
            'bank_name' => ['nullable', 'required_if:payment_mode,Bank Transfer', 'string', 'max:255'],
            'account_upi_address' => ['nullable', 'required_unless:payment_mode,Cash', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'earnings' => ['sometimes', 'array', 'min:1'],
            'earnings.*.name' => ['required_with:earnings', 'string', 'max:100'],
            'earnings.*.amount' => ['required_with:earnings', 'numeric', 'min:0'],
            'deductions' => ['sometimes', 'array'],
            'deductions.*.name' => ['required_with:deductions', 'string', 'max:100'],
            'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'],
            'confirmed' => ['required', 'accepted'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $employee = $this->employee((string) $request->input('employee_id'));
        if (! $employee) {
            return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
        }
        $month = $this->month($request->input('salary_month'));
        $preview = $this->calculator->preview($employee, $month);
        $earnings = $request->has('earnings') ? $this->moneyItems($request->input('earnings')) : $preview['earnings'];
        $deductions = $request->has('deductions') ? $this->moneyItems($request->input('deductions')) : $preview['deductions'];
        $gross = round(collect($earnings)->sum('amount'), 2);
        $totalDeductions = round(collect($deductions)->sum('amount'), 2);

        try {
            $salary = DB::transaction(fn () => Salary::create([
                'user_id' => $employee->id,
                'salary_month' => $month->toDateString(),
                'gross_earnings' => $gross,
                'total_deductions' => $totalDeductions,
                'net_payable' => max(0, round($gross - $totalDeductions, 2)),
                'earnings' => $earnings,
                'deductions' => $deductions,
                'attendance_summary' => $preview['attendance_summary'],
                'payment_date' => $request->input('payment_date'),
                'payment_mode' => $request->input('payment_mode'),
                'bank_name' => $request->input('bank_name'),
                'account_upi_address' => $request->input('account_upi_address'),
                'remarks' => $request->input('remarks'),
                'status' => 'Paid',
                'created_by' => $request->user()->id,
            ]));
        } catch (QueryException $exception) {
            if (Salary::where('user_id', $employee->id)->whereDate('salary_month', $month)->exists()) {
                return response()->json(['status' => false, 'message' => 'Salary has already been created for this employee and month.'], 409);
            }
            throw $exception;
        }

        return response()->json(['status' => true, 'message' => 'Salary created successfully.', 'data' => $this->salaryData($salary->load('employee'))], 201);
    }

    public function myHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['year' => ['sometimes', 'integer', 'min:2000', 'max:2100']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $employee = $request->user();
        $query = Salary::where('user_id', $employee->id)->orderByDesc('salary_month');
        if ($request->filled('year')) {
            $query->whereYear('salary_month', $request->integer('year'));
        }
        $salaries = $query->get()->map(fn (Salary $salary) => $this->salaryData($salary));
        $ctc = (float) ($employee->monthly_salary ?? 0);

        return response()->json(['status' => true, 'message' => 'Salary history retrieved successfully.', 'data' => [
            'current_monthly_ctc' => $ctc,
            'current_ctc_breakdown' => ['basic' => round($ctc * .5, 2), 'hra' => round($ctc * .25, 2), 'allowances' => round($ctc * .25, 2)],
            'recent_payslips' => $salaries,
        ]]);
    }

    public function payslip(Request $request, Salary $salary): Response
    {
        if ($request->user()->role !== 'admin' && $salary->user_id !== $request->user()->id) {
            abort(403, 'You are not allowed to access this payslip.');
        }
        $salary->load('employee');
        $employee = $salary->employee;
        $rows = fn (array $items) => collect($items)->map(fn ($item) => '<tr><td>'.e($item['name']).'</td><td style="text-align:right">&#8377;'.number_format($item['amount'], 2).'</td></tr>')->implode('');
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Payslip</title><style>body{font-family:Arial;max-width:760px;margin:35px auto;color:#172033}h1{color:#2463eb}.meta{display:flex;justify-content:space-between}table{width:100%;border-collapse:collapse;margin:20px 0}td,th{padding:10px;border-bottom:1px solid #ddd}th{text-align:left;background:#f5f7fb}.total{font-size:20px;font-weight:bold}</style></head><body><h1>Payslip</h1><div class="meta"><div><b>'.e($employee->name).'</b><br>'.e($employee->employee_id).'<br>'.e($employee->designation).'</div><div>'.e($salary->salary_month->format('F Y')).'<br>Paid: '.e($salary->payment_date->format('d M Y')).'</div></div><h3>Earnings</h3><table>'.$rows($salary->earnings).'</table><h3>Deductions</h3><table>'.$rows($salary->deductions).'</table><table><tr><th>Gross Earnings</th><td>&#8377;'.number_format((float) $salary->gross_earnings, 2).'</td></tr><tr><th>Total Deductions</th><td>&#8377;'.number_format((float) $salary->total_deductions, 2).'</td></tr><tr class="total"><th>Net Pay</th><td>&#8377;'.number_format((float) $salary->net_payable, 2).'</td></tr></table></body></html>';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Disposition' => $disposition.'; filename="payslip-'.$employee->employee_id.'-'.$salary->salary_month->format('Y-m').'.html"']);
    }

    private function employee(string $id): ?User
    {
        return User::where('role', 'employee')->where(fn ($query) => $query->where('id', $id)->orWhere('employee_id', $id))->first();
    }

    private function month(?string $month): Carbon
    {
        return Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
    }

    private function moneyItems(array $items): array
    {
        return collect($items)->map(fn ($item) => ['name' => trim($item['name']), 'amount' => round((float) $item['amount'], 2)])->values()->all();
    }

    private function employeeData(User $employee): array
    {
        return $employee->only(['id', 'employee_id', 'name', 'avatar', 'designation', 'department', 'date_of_joining', 'salary_type', 'monthly_salary']);
    }

    private function salaryAmounts(Salary $salary): array
    {
        return ['attendance_summary' => $salary->attendance_summary, 'earnings' => $salary->earnings, 'deductions' => $salary->deductions,
            'gross_earnings' => (float) $salary->gross_earnings, 'total_deductions' => (float) $salary->total_deductions, 'net_payable' => (float) $salary->net_payable];
    }

    private function paymentData(Salary $salary): array
    {
        return ['payment_date' => $salary->payment_date->toDateString(), 'payment_mode' => $salary->payment_mode, 'bank_name' => $salary->bank_name,
            'account_upi_address' => $salary->account_upi_address, 'remarks' => $salary->remarks, 'status' => $salary->status];
    }

    private function salaryData(Salary $salary): array
    {
        return ['id' => $salary->id, 'employee' => $salary->relationLoaded('employee') ? $this->employeeData($salary->employee) : null,
            'salary_month' => $salary->salary_month->format('Y-m'), 'month_label' => $salary->salary_month->format('F Y'), ...$this->salaryAmounts($salary),
            ...$this->paymentData($salary), 'payslip_url' => $this->payslipUrl($salary)];
    }

    private function payslipUrl(Salary $salary): string
    {
        return url("/api/admin/salaries/{$salary->id}/payslip");
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status' => false, 'message' => 'Validation error', 'errors' => $errors], 422);
    }
}
