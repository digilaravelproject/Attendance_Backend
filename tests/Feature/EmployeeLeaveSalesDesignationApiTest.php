<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeLeaveSalesDesignationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_and_remove_designation_and_sales_target_fields(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $designation = Designation::create(['name' => 'Sales Executive', 'hierarchy_level' => 'junior']);
        $otherDesignation = Designation::create(['name' => 'Sales Manager', 'hierarchy_level' => 'manager']);
        $employee = User::factory()->create(['role' => 'employee', 'designation_id' => $otherDesignation->id]);

        $this->actingAs($admin)->postJson("/api/admin/designations/{$designation->id}/employees", [
            'employee_ids' => [$employee->id],
        ])->assertOk()->assertJsonPath('data.employees_count', 1);
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'designation_id' => $designation->id]);

        $this->actingAs($admin)->deleteJson("/api/admin/designations/{$designation->id}/employees/{$employee->id}")
            ->assertOk()->assertJsonPath('data.employees_count', 0);

        $payload = [
            'employee_id' => 'SALES-01', 'name' => 'Sales Person', 'mobile_number' => '9876543210',
            'email' => 'sales@example.com', 'emergency_contact' => '9876543211',
            'address' => 'Pune', 'designation_id' => $designation->id, 'monthly_salary' => 60000,
            'date_of_joining' => '2026-09-21', 'skills' => ['Sales'],
            'sales_target_enabled' => true,
        ];
        $this->actingAs($admin)->postJson('/api/admin/employees', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['sales_target_metric_type', 'sales_target', 'sales_target_period']);

        $created = $this->actingAs($admin)->postJson('/api/admin/employees', [
            ...$payload,
            'sales_target_metric_type' => 'Revenue',
            'sales_target' => 500000,
            'sales_target_period' => 'Monthly',
            'incentive_commission_percent' => 5,
        ])->assertCreated()->assertJsonPath('data.sales_target_period', 'Monthly')
            ->assertJsonPath('data.sales_target', '500000.00');
        $id = $created->json('data.id');

        $this->actingAs($admin)->patchJson("/api/admin/employees/{$id}", [
            'sales_target_enabled' => false,
        ])->assertOk()->assertJsonPath('data.sales_target', null);
    }

    public function test_employee_leave_is_scoped_assigned_and_holidays_are_year_filtered(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $managerDesignation = Designation::create(['name' => 'Manager', 'hierarchy_level' => 'manager']);
        $manager = User::factory()->create(['role' => 'employee', 'designation_id' => $managerDesignation->id]);
        $employee = User::factory()->create(['role' => 'employee']);
        $otherEmployee = User::factory()->create(['role' => 'employee']);
        $type = LeaveType::create(['name' => 'Casual Leave', 'code' => 'CL', 'annual_allowance' => 12]);

        $this->actingAs($employee)->getJson('/api/admin/leave-approvers')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($employee)->postJson('/api/admin/leave-requests', [
            'leave_type_id' => $type->id, 'from_date' => '2026-09-21',
            'to_date' => '2026-09-21', 'session' => '1st Half', 'reason' => 'Appointment',
        ])->assertUnprocessable()->assertJsonValidationErrors('assigned_to_user_id');

        $leave = $this->actingAs($employee)->post('/api/admin/leave-requests', [
            'leave_type_id' => $type->id,
            'from_date' => '2026-09-21',
            'to_date' => '2026-09-21',
            'session' => '1st Half',
            'reason' => 'Appointment',
            'assigned_to_user_id' => $manager->id,
            'attachment' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.assigned_to_user_id', $manager->id)
            ->assertJsonPath('data.total_days', '0.50');
        $leaveId = $leave->json('data.id');

        $this->actingAs($otherEmployee)->getJson('/api/admin/leave-requests')
            ->assertOk()->assertJsonPath('counts.all', 0);
        $this->actingAs($otherEmployee)->getJson("/api/admin/leave-requests/{$leaveId}")->assertForbidden();
        $this->actingAs($otherEmployee)->postJson("/api/admin/leave-requests/{$leaveId}/approve")->assertForbidden();
        $this->actingAs($manager)->postJson("/api/admin/leave-requests/{$leaveId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'Approved');
        $this->actingAs($employee)->getJson('/api/admin/leave-requests?status=approved&year=2026')
            ->assertOk()->assertJsonPath('counts.approved', 1)
            ->assertJsonPath('leave_balances.0.taken', 0.5);
        $this->actingAs($employee)->getJson('/api/admin/leave-requests/calendar?year=2026')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->postJson('/api/admin/holidays', [
            'name' => 'Founders Day', 'date' => '2026-11-10', 'type' => 'Optional',
        ])->assertCreated();
        $this->actingAs($employee)->getJson('/api/admin/holidays?year=2026')
            ->assertOk()->assertJsonPath('summary.optional', 1)
            ->assertJsonPath('months.0.month', 'November');
        $this->actingAs($employee)->getJson('/api/admin/holidays?year=2025')
            ->assertOk()->assertJsonPath('summary.total', 0);
    }
}
