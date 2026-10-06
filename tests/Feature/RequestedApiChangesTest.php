<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestedApiChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_permission_list_and_all_users_are_public_and_actions_are_removed(): void
    {
        $this->seed(PermissionSeeder::class);
        User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'Public Permission Role', 'status' => true]);
        $role->permissions()->sync(Permission::limit(2)->pluck('id'));

        $this->getJson("/api/admin/permissions?role_id={$role->id}")
            ->assertOk()
            ->assertJsonPath('role.id', $role->id)
            ->assertJsonPath('assigned_permissions_count', 2)
            ->assertJsonMissingPath('modules.0.actions');

        $this->getJson('/api/admin/employees/all-users')
            ->assertOk()
            ->assertJsonPath('counts.total_users', 1);
    }

    public function test_upcoming_birthdays_reads_the_saved_date_of_birth_for_admins(): void
    {
        Carbon::setTestNow('2026-10-06 09:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'name' => 'Birthday Employee',
            'date_of_birth' => '1995-10-20',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/birthdays/upcoming?days=30')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Birthday Employee')
            ->assertJsonPath('data.0.birthday', '2026-10-20')
            ->assertJsonPath('data.0.days_until', 14);

        $this->assertDatabaseHas('users', [
            'name' => 'Birthday Employee',
            'date_of_birth' => '1995-10-20',
        ]);
    }

    public function test_assignee_can_handover_a_running_task_with_an_audit_record(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $creator = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $source = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $destination = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $task = Task::create([
            'task_name' => 'Finish attendance API',
            'created_by' => $creator->id,
            'status' => 'In Progress',
            'is_timer_running' => true,
            'timer_started_at' => now()->subMinutes(30),
        ]);
        $task->assignees()->attach($source->id, ['assigned_by' => $creator->id]);

        $this->withHeader('X-User-Id', (string) $source->id)
            ->postJson("/api/admin/tasks/{$task->id}/handover", [
                'to_user_id' => $destination->id,
                'reason' => 'Current workload is full; backend expertise is needed.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.task.assignees.0.id', $destination->id)
            ->assertJsonPath('data.task.is_timer_running', false)
            ->assertJsonPath('data.handover.from_user.id', $source->id)
            ->assertJsonPath('data.handover.to_user.id', $destination->id);

        $this->assertDatabaseMissing('task_user', ['task_id' => $task->id, 'user_id' => $source->id]);
        $this->assertDatabaseHas('task_user', ['task_id' => $task->id, 'user_id' => $destination->id]);
        $this->assertDatabaseHas('task_handovers', [
            'task_id' => $task->id,
            'from_user_id' => $source->id,
            'to_user_id' => $destination->id,
            'handed_over_by' => $source->id,
        ]);
        $this->assertDatabaseHas('task_time_logs', [
            'task_id' => $task->id,
            'user_id' => $source->id,
            'action' => 'stop',
            'duration_seconds' => 1800,
        ]);
    }
}
