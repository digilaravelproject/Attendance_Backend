<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAggregateLeaveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_can_be_assigned_to_multiple_approvers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $managerDesignation = Designation::create(['name' => 'Manager', 'hierarchy_level' => 'manager']);
        $managerOne = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation_id' => $managerDesignation->id]);
        $managerTwo = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation_id' => $managerDesignation->id]);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $type = LeaveType::create(['name' => 'Casual Leave', 'code' => 'CL', 'annual_allowance' => 12]);

        $response = $this->actingAs($employee)->postJson('/api/admin/leave-requests', [
            'leave_type_id' => $type->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-02',
            'reason' => 'Family event',
            'assigned_to_user_ids' => [$managerOne->id, $managerTwo->id, $admin->id],
        ])->assertCreated()->assertJsonCount(3, 'data.assignees');

        $leaveId = $response->json('data.id');
        $this->assertDatabaseCount('leave_request_assignees', 3);
        $this->actingAs($managerTwo)->getJson('/api/admin/leave-requests')
            ->assertOk()->assertJsonPath('pagination.total', 1);
        $this->actingAs($managerTwo)->postJson("/api/admin/leave-requests/{$leaveId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'Approved');
    }

    public function test_aggregate_user_employee_leave_and_report_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $department = Department::create(['name' => 'Design']);
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'Active', 'employee_id' => 'EMP-100',
            'department_id' => $department->id, 'department' => 'Design',
        ]);
        $type = LeaveType::create(['name' => 'Sick Leave', 'code' => 'SL', 'annual_allowance' => 8]);
        LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $type->id,
            'from_date' => '2026-09-10', 'to_date' => '2026-09-10', 'total_days' => 1,
            'reason' => 'Medical', 'status' => 'Approved',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/employees/all-users')->assertOk()
            ->assertJsonPath('counts.total_users', 2)
            ->assertJsonPath('counts.admins', 1)
            ->assertJsonPath('counts.employees', 1);
        $this->actingAs($admin)->getJson("/api/admin/employees/{$employee->id}")->assertOk()
            ->assertJsonPath('data.summary.leave_requests.total', 1)
            ->assertJsonCount(1, 'data.leave_requests');
        $this->actingAs($admin)->getJson('/api/admin/employees/leaves?status=approved')->assertOk()
            ->assertJsonPath('pagination.total', 1);
        $this->actingAs($admin)->getJson("/api/admin/employees/leave-reports?from_date=2026-09-01&to_date=2026-09-30&department_id={$department->id}&leave_type_id={$type->id}&status=approved")
            ->assertOk()->assertJsonPath('summary.total_requests', 1)
            ->assertJsonPath('summary.approved', 1)->assertJsonCount(1, 'data');
    }
}
