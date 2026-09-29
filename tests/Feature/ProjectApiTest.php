<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_projects_and_assign_multiple_employees(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $employeeOne = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'employee_id' => 'EMP-1']);
        $employeeTwo = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'employee_id' => 'EMP-2']);

        $created = $this->actingAs($admin)->post('/api/admin/projects', [
            'name' => 'Website Redesign',
            'description' => 'Redesign and develop the company website.',
            'category' => 'Web Development',
            'start_date' => '2026-09-29',
            'end_date' => '2026-10-29',
            'status' => 'Not Started',
            'employee_ids' => [$employeeOne->id, $employeeTwo->id],
            'files' => [UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonCount(2, 'data.team')
            ->assertJsonCount(1, 'data.files')
            ->assertJsonPath('data.tasks.is_static', true);
        $id = $created->json('data.id');

        $this->actingAs($admin)->getJson('/api/admin/projects/total')->assertOk()
            ->assertJsonPath('data.total_projects', 1)
            ->assertJsonPath('data.not_started', 1);
        $this->actingAs($admin)->getJson('/api/admin/projects/search?query=website')->assertOk()
            ->assertJsonPath('total', 1);
        $this->actingAs($admin)->getJson("/api/admin/projects/{$id}")->assertOk()
            ->assertJsonPath('data.overview.status', 'Not Started')
            ->assertJsonCount(2, 'data.team');
        $this->actingAs($admin)->patchJson("/api/admin/projects/{$id}", [
            'status' => 'In Progress', 'progress' => 41,
        ])->assertOk()->assertJsonPath('data.progress', 41);
        $this->actingAs($admin)->deleteJson("/api/admin/projects/{$id}/employees/{$employeeTwo->id}")
            ->assertOk()->assertJsonCount(1, 'data.team');
        $this->actingAs($admin)->deleteJson("/api/admin/projects/{$id}")->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $id]);
    }

    public function test_manager_can_manage_and_employee_only_reads_assigned_projects(): void
    {
        $managerDesignation = Designation::create(['name' => 'Project Manager', 'hierarchy_level' => 'manager']);
        $manager = User::factory()->create([
            'role' => 'employee', 'status' => 'Active', 'designation_id' => $managerDesignation->id,
        ]);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $otherEmployee = User::factory()->create(['role' => 'employee', 'status' => 'Active']);

        $project = $this->actingAs($manager)->postJson('/api/admin/projects', [
            'name' => 'Mobile App Development',
            'description' => 'Build the employee mobile application.',
            'category' => 'Mobile Development',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'status' => 'In Progress',
            'progress' => 40,
            'employee_ids' => [$manager->id, $employee->id],
        ])->assertCreated()->json('data');

        $this->actingAs($employee)->getJson('/api/admin/projects')->assertForbidden();
        $this->actingAs($employee)->getJson('/api/admin/projects/assigned')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $project['id']);
        $this->actingAs($employee)->getJson("/api/admin/projects/assigned/{$project['id']}")->assertOk()
            ->assertJsonStructure(['data' => ['overview', 'tasks', 'team', 'files', 'timeline']]);
        $this->actingAs($otherEmployee)->getJson('/api/admin/projects/assigned')->assertOk()
            ->assertJsonPath('total', 0);
        $this->actingAs($otherEmployee)->getJson("/api/admin/projects/assigned/{$project['id']}")->assertNotFound();
    }
}
