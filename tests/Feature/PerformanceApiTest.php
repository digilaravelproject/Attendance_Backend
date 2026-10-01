<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Designation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_performance_visibility_and_scores_are_calculated_from_projects_tasks_and_reviews(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $managerDesignation = Designation::create(['name' => 'Engineering Manager', 'hierarchy_level' => 'manager']);
        $manager = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation_id' => $managerDesignation->id]);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'reporting_manager_id' => $manager->id]);
        $other = User::factory()->create(['role' => 'employee', 'status' => 'Active']);

        $project = Project::create([
            'name' => 'Dynamic API', 'description' => 'Performance API', 'category' => 'Backend',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'In Progress',
            'progress' => 80, 'created_by' => $manager->id,
        ]);
        $project->members()->attach($employee->id, ['assigned_by' => $manager->id]);

        $completed = Task::create([
            'task_name' => 'Completed endpoint', 'project_id' => $project->id, 'priority' => 'High',
            'status' => 'Completed', 'start_date' => '2026-10-01', 'due_date' => '2026-10-10',
            'completed_at' => '2026-10-09 18:00:00', 'created_by' => $manager->id,
        ]);
        $pending = Task::create([
            'task_name' => 'Pending endpoint', 'project_id' => $project->id, 'priority' => 'Medium',
            'status' => 'In Progress', 'start_date' => '2026-10-03', 'due_date' => '2026-10-20',
            'created_by' => $manager->id,
        ]);
        $completed->assignees()->attach($employee->id, ['assigned_by' => $manager->id]);
        $pending->assignees()->attach($employee->id, ['assigned_by' => $manager->id]);

        $this->actingAs($manager)->postJson("/api/admin/performance/employees/{$employee->id}/quality-reviews", [
            'task_id' => $completed->id,
            'deliverable_accuracy' => 90, 'deadline_adherence' => 90,
            'defect_prevention' => 90, 'collaboration' => 90,
            'feedback' => 'Calculated review data.', 'reviewed_at' => '2026-10-11 09:00:00',
        ])->assertCreated();

        $this->actingAs($employee)->getJson('/api/admin/performance/employees?month=2026-10')
            ->assertOk()->assertJsonPath('summary.total_employees', 1)
            ->assertJsonPath('data.0.employee.id', $employee->id)
            ->assertJsonPath('data.0.score', 71.5);
        $this->actingAs($employee)->getJson("/api/admin/performance/employees/{$other->id}?month=2026-10")->assertForbidden();

        $this->actingAs($manager)->getJson('/api/admin/performance/employees?month=2026-10')
            ->assertOk()->assertJsonPath('summary.total_employees', 2);
        $this->actingAs($manager)->getJson("/api/admin/performance/employees/{$employee->id}?month=2026-10")
            ->assertOk()->assertJsonPath('data.metrics.task_completion.completed', 1)
            ->assertJsonPath('data.metrics.task_completion.total', 2)
            ->assertJsonPath('data.metrics.timely_submission.percent', 100);

        $this->actingAs($admin)->getJson('/api/admin/performance/employees?month=2026-10')
            ->assertOk()->assertJsonPath('summary.total_employees', 3);
    }

    public function test_drilldowns_and_messages_return_database_data(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $managerDesignation = Designation::create(['name' => 'Manager', 'hierarchy_level' => 'manager']);
        $manager = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation_id' => $managerDesignation->id]);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'reporting_manager_id' => $manager->id]);

        Attendance::create(['user_id' => $employee->id, 'attendance_date' => '2026-10-01', 'status' => 'Present', 'working_minutes' => 480]);
        $leaveType = LeaveType::create(['name' => 'Casual', 'code' => 'CL', 'annual_allowance' => 12, 'status' => 'Active']);
        LeaveRequest::create([
            'user_id' => $employee->id, 'leave_type_id' => $leaveType->id,
            'from_date' => '2026-10-05', 'to_date' => '2026-10-05', 'total_days' => 1,
            'reason' => 'Personal', 'status' => 'Approved',
        ]);

        $this->actingAs($employee)->getJson("/api/admin/performance/employees/{$employee->id}/attendance?month=2026-10")
            ->assertOk()->assertJsonPath('data.summary.present', 1);
        $this->actingAs($employee)->getJson("/api/admin/performance/employees/{$employee->id}/leave?month=2026-10")
            ->assertOk()->assertJsonPath('data.summary.approved_days_in_period', 1)
            ->assertJsonPath('data.balances.0.balance', 11);

        $this->actingAs($manager)->postJson("/api/admin/performance/employees/{$employee->id}/messages", ['message' => 'Please review your task deadline.'])
            ->assertCreated()->assertJsonPath('data.message', 'Please review your task deadline.');
        $this->actingAs($manager)->getJson("/api/admin/performance/employees/{$employee->id}/messages")
            ->assertOk()->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.sender_id', $manager->id);
        $this->actingAs($employee)->getJson("/api/admin/performance/employees/{$employee->id}/messages")
            ->assertOk()->assertJsonPath('pagination.total', 1);
        $this->actingAs($employee)->postJson("/api/admin/performance/employees/{$employee->id}/messages", ['message' => 'Self message'])
            ->assertForbidden();
    }
}
