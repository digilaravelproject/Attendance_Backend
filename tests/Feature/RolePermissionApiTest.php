<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_permissions_are_grouped_and_totalled(): void
    {
        $this->actingAs($this->admin)->getJson('/api/admin/permissions/total')
            ->assertOk()
            ->assertJsonPath('total_permissions', 83)
            ->assertJsonPath('total_categories', 13);

        $this->actingAs($this->admin)->getJson('/api/admin/permissions')
            ->assertOk()
            ->assertJsonPath('total_permissions', 83)
            ->assertJsonPath('modules.0.module', 'Profile & Documents')
            ->assertJsonPath('modules.0.total_permissions', 10);
    }

    public function test_role_crud_uses_department_status_and_granular_permissions(): void
    {
        $permissionIds = Permission::limit(4)->pluck('id')->all();

        $created = $this->actingAs($this->admin)->postJson('/api/admin/roles', [
            'name' => 'Senior Flutter Developer',
            'department' => 'Engineering',
            'description' => 'Builds mobile applications',
            'status' => true,
            'permission_ids' => $permissionIds,
        ])->assertCreated()
            ->assertJsonPath('data.granted_permissions', 4)
            ->assertJsonPath('data.total_permissions', 83);

        $roleId = $created->json('data.id');

        $this->actingAs($this->admin)->putJson("/api/admin/roles/{$roleId}", [
            'status' => false,
            'permission_ids' => [$permissionIds[0]],
        ])->assertOk()
            ->assertJsonPath('data.status', false)
            ->assertJsonPath('data.granted_permissions', 1);

        $this->actingAs($this->admin)->getJson("/api/admin/permissions/{$roleId}")
            ->assertOk()
            ->assertJsonPath('assigned_permissions_count', 1);

        $this->actingAs($this->admin)->deleteJson("/api/admin/roles/{$roleId}")->assertOk();
    }

    public function test_removed_role_user_routes_are_not_available(): void
    {
        $this->actingAs($this->admin)->getJson('/api/admin/users')->assertNotFound();
        $this->actingAs($this->admin)->deleteJson('/api/admin/roles/1/users/1')->assertNotFound();
    }
}
