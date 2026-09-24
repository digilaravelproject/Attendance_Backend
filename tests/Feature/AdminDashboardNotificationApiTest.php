<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_action_creates_admin_and_employee_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Manager']);
        $employee = User::factory()->create(['role' => 'employee', 'name' => 'Original Name']);

        $this->actingAs($admin)->patchJson("/api/admin/employees/{$employee->id}", [
            'name' => 'Updated Name',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $admin->id,
            'actor_id' => $admin->id,
            'type' => 'admin_action',
            'module' => 'employees',
            'action' => 'updated',
        ]);
        $employeeNotification = Notification::where('recipient_id', $employee->id)->firstOrFail();
        $this->assertSame('Employee Profile Updated', $employeeNotification->title);

        $this->actingAs($employee)->getJson('/api/admin/notifications?status=unread')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.id', $employeeNotification->id);

        $this->actingAs($employee)->patchJson("/api/admin/notifications/{$employeeNotification->id}/read")
            ->assertOk()->assertJsonPath('data.is_read', true);

        $this->actingAs($admin)->getJson("/api/admin/notifications/{$employeeNotification->id}")
            ->assertNotFound();

        $this->actingAs($employee)->deleteJson("/api/admin/notifications/{$employeeNotification->id}")
            ->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $employeeNotification->id]);
    }

    public function test_admin_dashboard_and_module_statistics_have_mobile_summary_shape(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'company_name' => 'ABC Solutions']);
        User::factory()->count(2)->create(['role' => 'employee', 'status' => 'Active']);

        $this->actingAs($admin)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'ABC Solutions')
            ->assertJsonPath('data.todays_summary.total_employees', 2)
            ->assertJsonStructure(['data' => [
                'greeting', 'unread_notifications',
                'todays_summary' => ['present', 'absent', 'on_leave', 'present_percentage'],
                'recent_activities',
            ]]);

        $this->actingAs($admin)->getJson('/api/admin/modules/statistics')
            ->assertOk()
            ->assertJsonPath('data.employees', 2)
            ->assertJsonPath('data.present_today', 0)
            ->assertJsonStructure(['data' => ['employees', 'present_today', 'pending_tasks', 'modules']]);
    }
}
