<?php

namespace Tests\Feature;

use App\Mail\WelcomeEmployeeMail;
use App\Models\Designation;
use App\Models\PasswordOtp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeePortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_employee_onboarding_generates_expected_password_and_login_returns_permissions(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $designation = Designation::create(['name' => 'Developer', 'hierarchy_level' => 'junior']);
        $permission = Permission::create([
            'module' => 'Attendance & Regularization',
            'module_slug' => 'attendance_regularization',
            'name' => 'Check-In / Check-Out',
            'action' => 'check_in_check_out',
            'description' => 'Mark attendance',
        ]);
        $role = Role::create(['name' => 'Employee', 'status' => true]);
        $role->permissions()->attach($permission);

        $response = $this->actingAs($admin)->postJson('/api/admin/employees', [
            'employee_id' => 'EMP-001',
            'name' => 'Darshan Kondekar',
            'mobile_number' => '9876543210',
            'email' => 'darshan@example.com',
            'emergency_contact' => '9876500000',
            'address' => 'Pune, Maharashtra',
            'designation_id' => $designation->id,
            'monthly_salary' => 80000,
            'date_of_joining' => '2026-09-01',
            'skills' => ['PHP'],
            'role_ids' => [$role->id],
        ])->assertCreated()->assertJsonPath('email_sent', true);

        Mail::assertSent(WelcomeEmployeeMail::class, fn (WelcomeEmployeeMail $mail) => $mail->hasTo('darshan@example.com') && $mail->plainPassword === 'darshankondekar@123'
        );

        $this->postJson('/api/admin/login', [
            'email' => 'darshan@example.com',
            'password' => 'darshankondekar@123',
        ])->assertOk()
            ->assertJsonPath('data.employee_id', 'EMP-001')
            ->assertJsonPath('data.roles.0.name', 'Employee')
            ->assertJsonPath('data.permissions.0.action', 'check_in_check_out')
            ->assertJsonPath('data.permissions_by_module.0.module_slug', 'attendance_regularization')
            ->assertJsonStructure(['access_token', 'token_type']);

        $this->assertDatabaseHas('role_user', [
            'role_id' => $role->id,
            'user_id' => $response->json('data.id'),
        ]);

        $this->postJson('/api/admin/forgot-password', [
            'email' => 'darshan@example.com',
        ])->assertOk();
        $otp = PasswordOtp::where('email', 'darshan@example.com')->value('otp');

        $this->postJson('/api/admin/reset-password', [
            'email' => 'darshan@example.com',
            'otp' => $otp,
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ])->assertOk();

        $this->postJson('/api/admin/login', [
            'email' => 'darshan@example.com',
            'password' => 'NewPassword@123',
        ])->assertOk()->assertJsonPath('data.role', 'employee');
    }

    public function test_employee_can_check_in_check_out_and_get_dashboard_birthdays_and_history(): void
    {
        Carbon::setTestNow('2026-09-21 08:55:00');
        $shift = Shift::create([
            'name' => 'General Shift',
            'shift_type' => 'General',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration' => '01:00',
            'status' => 'Active',
        ]);
        $shift->forceFill([
            'code' => 'GENERAL',
            'grace_period_minutes' => 15,
            'half_day_after_minutes' => 240,
            'overtime_enabled' => true,
            'overtime_starts_after_minutes' => 480,
        ])->save();

        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'employee_id' => 'EMP-002',
            'name' => 'Rohit Sharma',
            'date_of_birth' => '1990-09-21',
            'designation' => 'UI/UX Designer',
            'assigned_shift_id' => $shift->id,
        ]);
        User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'name' => 'Neha Gupta',
            'date_of_birth' => '1995-09-23',
            'designation' => 'Marketing Executive',
        ]);

        $this->actingAs($employee)->postJson('/api/admin/attendance/check-in', [
            'latitude' => 18.5204,
            'longitude' => 73.8567,
        ])->assertCreated()
            ->assertJsonPath('data.check_in', '08:55 AM')
            ->assertJsonPath('data.status', 'Present');

        $this->actingAs($employee)->postJson('/api/admin/attendance/check-in')
            ->assertStatus(409);

        Carbon::setTestNow('2026-09-21 18:25:00');
        $this->actingAs($employee)->postJson('/api/admin/attendance/check-out')
            ->assertOk()
            ->assertJsonPath('data.check_out', '06:25 PM')
            ->assertJsonPath('data.working_minutes', 510)
            ->assertJsonPath('data.overtime_minutes', 30);

        $this->actingAs($employee)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.current_shift.name', 'General Shift')
            ->assertJsonPath('data.todays_summary.working_minutes', 510)
            ->assertJsonPath('data.todays_birthdays.0.name', 'Rohit Sharma');

        $this->actingAs($employee)->getJson('/api/admin/birthdays/upcoming?days=10')
            ->assertOk()
            ->assertJsonPath('data.0.days_until', 0)
            ->assertJsonPath('data.1.days_until', 2);

        $this->actingAs($employee)->getJson('/api/admin/attendance/history?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.recent_records.0.date', '2026-09-21');
    }

    public function test_admin_token_cannot_access_employee_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_employee_can_use_shared_profile_password_and_document_endpoints(): void
    {
        Storage::fake('public');
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'password' => bcrypt('CurrentPassword@123'),
        ]);

        $profile = $this->actingAs($employee)->post('/api/admin/update-profile', [
            'name' => 'Updated Employee',
            'phone' => '+91 98765 43210',
            'department' => 'Design',
            'designation' => 'UI/UX Designer',
            'employee_id' => 'EMP1025',
            'documents' => [UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf')],
        ], ['Accept' => 'application/json']);

        $profile->assertOk()
            ->assertJsonPath('data.name', 'Updated Employee')
            ->assertJsonPath('data.employee_id', 'EMP1025')
            ->assertJsonCount(1, 'data.documents');
        $documentId = $profile->json('data.documents.0.id');

        $this->actingAs($employee)->postJson('/api/admin/update-password', [
            'current_password' => 'CurrentPassword@123',
            'new_password' => 'UpdatedPassword@123',
            'confirm_password' => 'UpdatedPassword@123',
        ])->assertOk();

        $this->postJson('/api/admin/login', [
            'email' => $employee->email,
            'password' => 'UpdatedPassword@123',
        ])->assertOk()->assertJsonPath('data.role', 'employee');

        $this->actingAs($employee)->deleteJson("/api/admin/documents/{$documentId}")
            ->assertOk();
        $this->assertDatabaseMissing('admin_documents', ['id' => $documentId]);
    }
}
