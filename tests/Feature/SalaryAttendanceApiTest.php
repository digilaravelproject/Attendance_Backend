<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Salary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_view_all_employee_attendance_for_a_date(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $present = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'employee_id' => 'EMP001', 'name' => 'Present Employee']);
        User::factory()->create(['role' => 'employee', 'status' => 'Active', 'employee_id' => 'EMP002', 'name' => 'Absent Employee']);
        Attendance::create([
            'user_id' => $present->id,
            'attendance_date' => '2026-09-25',
            'check_in_at' => '2026-09-25 09:00:00',
            'check_out_at' => '2026-09-25 18:00:00',
            'working_minutes' => 480,
            'status' => 'Present',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/attendance?date=2026-09-25')
            ->assertOk()
            ->assertJsonPath('data.summary.total_employees', 2)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonCount(2, 'data.employees');
    }

    public function test_admin_can_preview_create_and_list_salary_and_employee_can_get_history_and_payslip(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'employee_id' => 'EMP100',
            'name' => 'Rahul Sharma',
            'department' => 'Sales',
            'designation' => 'Sales Executive',
            'monthly_salary' => 45000,
            'salary_type' => 'Monthly',
            'date_of_joining' => '2022-01-15',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/salaries/employee/EMP100?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.salary_created', false)
            ->assertJsonPath('data.gross_earnings', 45000)
            ->assertJsonStructure(['data' => ['attendance_summary', 'earnings', 'deductions', 'net_payable']]);

        $created = $this->actingAs($admin)->postJson('/api/admin/salaries', [
            'employee_id' => 'EMP100',
            'salary_month' => '2026-09',
            'payment_date' => '2026-09-25',
            'payment_mode' => 'Bank Transfer',
            'bank_name' => 'HDFC Bank',
            'account_upi_address' => 'XXXX1234',
            'earnings' => [
                ['name' => 'Basic', 'amount' => 22500],
                ['name' => 'HRA', 'amount' => 11250],
                ['name' => 'Allowances', 'amount' => 11250],
            ],
            'deductions' => [['name' => 'LWP', 'amount' => 2500]],
            'confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.net_payable', 42500)
            ->assertJsonPath('data.status', 'Paid');

        $salaryId = $created->json('data.id');
        $this->assertDatabaseHas('salaries', ['user_id' => $employee->id, 'net_payable' => 42500]);

        $this->actingAs($admin)->postJson('/api/admin/salaries', [
            'employee_id' => 'EMP100', 'salary_month' => '2026-09', 'payment_date' => '2026-09-25',
            'payment_mode' => 'Cash', 'confirmed' => true,
        ])->assertStatus(409);

        $this->actingAs($admin)->getJson('/api/admin/salaries?month=2026-09')
            ->assertOk()->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.employees.0.status', 'Created');

        $this->actingAs($employee)->getJson('/api/admin/salary-history')
            ->assertOk()
            ->assertJsonPath('data.current_monthly_ctc', 45000)
            ->assertJsonPath('data.recent_payslips.0.net_payable', 42500)
            ->assertJsonPath('data.recent_payslips.0.id', $salaryId);

        $this->actingAs($employee)->get("/api/admin/salaries/{$salaryId}/payslip")
            ->assertOk()->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Rahul Sharma');
    }

    public function test_employee_cannot_access_another_employees_payslip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $other = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $salary = Salary::create([
            'user_id' => $owner->id, 'salary_month' => '2026-08-01', 'gross_earnings' => 1000,
            'total_deductions' => 0, 'net_payable' => 1000, 'earnings' => [], 'deductions' => [],
            'attendance_summary' => [], 'payment_date' => '2026-09-01', 'payment_mode' => 'Cash',
            'status' => 'Paid', 'created_by' => $admin->id,
        ]);

        $this->actingAs($other)->get("/api/admin/salaries/{$salary->id}/payslip")->assertForbidden();
    }
}
