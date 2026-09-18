<?php

namespace Tests\Feature;

use App\Mail\WelcomeEmployeeMail;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShiftEmployeeOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_designation_shift_and_employee_screenshot_payloads(): void
    {
        Storage::fake('public');
        Mail::fake();

        $designation = $this->actingAs($this->admin)->postJson('/api/admin/designations', [
            'name' => 'Senior Flutter Developer',
            'hierarchy_level' => 'senior',
            'skills' => ['Flutter', 'Dart', 'Firebase'],
        ])->assertCreated()
            ->assertJsonPath('data.skills.0', 'Flutter');
        $designationId = $designation->json('data.id');

        $shift = $this->actingAs($this->admin)->postJson('/api/admin/shifts', [
            'name' => 'Morning Shift',
            'code' => 'MORNING',
            'shift_type' => 'Fixed Shift',
            'status' => true,
            'description' => 'Morning working shift for sales and operations team.',
            'start_time' => '10:00 AM',
            'end_time' => '07:00 PM',
            'cross_midnight' => false,
            'breaks_enabled' => true,
            'breaks' => [[
                'name' => 'Lunch Break',
                'type' => 'Paid',
                'start_time' => '01:00 PM',
                'end_time' => '02:00 PM',
            ]],
            'grace_period_minutes' => 15,
            'late_after_minutes' => 15,
            'minimum_working_minutes' => 480,
            'early_leaving_allowed' => false,
            'auto_mark_late' => true,
            'auto_mark_half_day' => true,
            'late_threshold_minutes' => 30,
            'half_day_after_minutes' => 240,
            'overtime_enabled' => true,
            'overtime_starts_after_minutes' => 480,
            'minimum_overtime_minutes' => 30,
            'overtime_calculation' => 'Hourly',
            'overtime_approval_required' => true,
            'working_days' => [[
                'day' => 'Monday', 'enabled' => true, 'start_time' => '10:00', 'end_time' => '19:00',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'MORNING')
            ->assertJsonPath('data.breaks.0.name', 'Lunch Break')
            ->assertJsonPath('data.gross_duration', '9h 00m')
            ->assertJsonPath('data.total_duration', '8h 00m');
        $shiftId = $shift->json('data.id');

        $employee = $this->actingAs($this->admin)->post('/api/admin/employees', [
            'avatar' => UploadedFile::fake()->image('profile.jpg'),
            'name' => 'Rahul Sharma',
            'employee_id' => 'EMP-2026-007',
            'gender' => 'Male',
            'date_of_birth' => '1996-05-15',
            'marital_status' => 'Single',
            'blood_group' => 'O+',
            'mobile_number' => '9876543210',
            'alternate_mobile_number' => '9876500001',
            'email' => 'rahul@example.com',
            'emergency_contact' => '9876500000',
            'street_address' => 'Flat 402, Sunshine Heights',
            'city' => 'Bengaluru',
            'postal_code' => '560038',
            'state' => 'Karnataka',
            'country' => 'India',
            'work_mode' => 'Office',
            'employee_type' => 'Full-time',
            'department' => 'Engineering',
            'designation_id' => $designationId,
            'team' => 'Team Alpha',
            'assigned_shift_id' => $shiftId,
            'date_of_joining' => '2026-09-17',
            'employment_status' => 'Active',
            'probation_period' => '3 Months',
            'notice_period' => '30 Days',
            'salary_type' => 'Monthly',
            'monthly_base_salary' => 60000,
            'sales_target_enabled' => false,
            'account_holder_name' => 'Rahul Sharma',
            'bank_name' => 'HDFC Bank',
            'account_number' => '50100456789123',
            'ifsc_code' => 'hdfc0001234',
            'branch_name' => 'Main Branch',
            'skills' => ['Flutter', 'Dart', 'Teamwork'],
        ], ['Accept' => 'application/json']);

        $employee->assertCreated()
            ->assertJsonPath('email_sent', true)
            ->assertJsonPath('data.employee_type', 'Full-time')
            ->assertJsonPath('data.assigned_shift.id', $shiftId)
            ->assertJsonPath('data.monthly_base_salary', '60000.00')
            ->assertJsonPath('data.ifsc_code', 'HDFC0001234');

        $employeeId = $employee->json('data.id');
        Mail::assertSent(WelcomeEmployeeMail::class, function (WelcomeEmployeeMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('rahul@example.com')
                && str_contains($html, 'Welcome to')
                && str_contains($html, 'Temporary Password');
        });

        $this->actingAs($this->admin)->putJson("/api/admin/shifts/{$shiftId}", [
            'employee_ids' => [$employeeId],
        ])->assertOk()->assertJsonPath('data.assigned_employees_count', 1);

        $this->actingAs($this->admin)->getJson("/api/admin/employees/{$employeeId}")
            ->assertOk()
            ->assertJsonPath('data.designation_details.skills.1', 'Dart');
    }
}
