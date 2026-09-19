<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentLeaveManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $designation = Designation::create([
            'name' => 'UI/UX Designer',
            'hierarchy_level' => 'senior',
        ]);
        $this->employee = User::create([
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'employee_id' => 'EMP1025',
            'mobile_number' => '9876543210',
            'designation_id' => $designation->id,
            'designation' => $designation->name,
            'team' => 'Design Team',
            'status' => 'Active',
        ]);
    }

    public function test_department_crud_member_assignment_and_details(): void
    {
        $created = $this->actingAs($this->admin)->postJson('/api/admin/departments', [
            'name' => 'Engineering',
            'description' => 'Handles engineering and product development activities.',
            'head_user_id' => $this->employee->id,
            'employee_ids' => [$this->employee->id],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Engineering')
            ->assertJsonPath('data.employee_count', 1)
            ->assertJsonPath('data.team_count', 1)
            ->assertJsonPath('data.head.id', $this->employee->id);

        $id = $created->json('data.id');
        $this->assertDatabaseHas('users', [
            'id' => $this->employee->id,
            'department_id' => $id,
            'department' => 'Engineering',
        ]);

        $this->actingAs($this->admin)->getJson('/api/admin/departments/search?query=engineer')
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($this->admin)->getJson("/api/admin/departments/{$id}")
            ->assertOk()->assertJsonCount(1, 'data.employees');
        $this->actingAs($this->admin)->patchJson("/api/admin/departments/{$id}", [
            'name' => 'Product Engineering',
        ])->assertOk()->assertJsonPath('data.name', 'Product Engineering');
        $this->assertDatabaseHas('users', ['id' => $this->employee->id, 'department' => 'Product Engineering']);

        $this->actingAs($this->admin)->deleteJson("/api/admin/departments/{$id}")->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->employee->id, 'department_id' => null]);
    }

    public function test_leave_filters_details_approval_calendar_and_reports(): void
    {
        $department = $this->actingAs($this->admin)->postJson('/api/admin/departments', [
            'name' => 'Design',
            'employee_ids' => [$this->employee->id],
        ])->assertCreated()->json('data');
        $type = $this->actingAs($this->admin)->postJson('/api/admin/leave-types', [
            'name' => 'Casual Leave',
            'code' => 'CL',
            'annual_allowance' => 12,
            'is_paid' => true,
        ])->assertCreated()->json('data');

        $leave = $this->actingAs($this->admin)->postJson('/api/admin/leave-requests', [
            'user_id' => $this->employee->id,
            'leave_type_id' => $type['id'],
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-22',
            'reason' => 'Personal work at hometown.',
            'contact_during_leave' => '9876543210',
            'attachment_name' => 'Train_Ticket.pdf',
            'attachment_path' => 'leave-attachments/Train_Ticket.pdf',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.total_days', '3.00')
            ->assertJsonPath('data.leave_balance.total', 12)
            ->json('data');

        $this->actingAs($this->admin)->getJson(
            "/api/admin/leave-requests?status=pending&department_id={$department['id']}&leave_type_id={$type['id']}&from_date=2026-09-01&to_date=2026-09-30"
        )->assertOk()
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.employee.name', 'Rahul Sharma');

        $this->actingAs($this->admin)->postJson("/api/admin/leave-requests/{$leave['id']}/approve", [
            'note' => 'Approved by manager.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'Approved')
            ->assertJsonPath('data.leave_balance.taken', 3);

        $this->actingAs($this->admin)->getJson('/api/admin/leave-requests/calendar?month=2026-09')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->admin)->getJson('/api/admin/leave-requests/reports?from_date=2026-01-01&to_date=2026-12-31')
            ->assertOk()
            ->assertJsonPath('summary.total_requests', 1)
            ->assertJsonPath('summary.total_days', 3);
        $this->actingAs($this->admin)->getJson("/api/admin/leave-requests/{$leave['id']}")
            ->assertOk()
            ->assertJsonPath('data.actions.0.action', 'Approved');
    }

    public function test_rejection_requires_a_note_and_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/admin/departments')->assertUnauthorized();

        $typeId = $this->actingAs($this->admin)->postJson('/api/admin/leave-types', [
            'name' => 'Sick Leave', 'code' => 'SL', 'annual_allowance' => 8,
        ])->json('data.id');
        $leaveId = $this->actingAs($this->admin)->postJson('/api/admin/leave-requests', [
            'user_id' => $this->employee->id,
            'leave_type_id' => $typeId,
            'from_date' => '2026-09-19',
            'to_date' => '2026-09-19',
            'reason' => 'Medical appointment.',
        ])->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/admin/leave-requests/{$leaveId}/reject")
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->actingAs($this->admin)->postJson("/api/admin/leave-requests/{$leaveId}/reject", [
            'note' => 'Insufficient supporting information.',
        ])->assertOk()->assertJsonPath('data.status', 'Rejected');
    }
}
