<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Holiday;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProjectWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_employee_can_read_lead_statuses_and_calculate_working_days(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        Holiday::create(['name' => 'Company Holiday', 'date' => '2026-11-03', 'type' => 'company']);

        $this->actingAs($employee)->getJson('/api/admin/lead-statuses')->assertOk();
        $this->actingAs($employee)->getJson('/api/admin/working-days?start_date=2026-11-01&end_date=2026-11-14')
            ->assertOk()
            ->assertJsonPath('data.calendar_days', 14)
            ->assertJsonPath('data.working_days', 10)
            ->assertJsonPath('data.excluded.sundays', 2)
            ->assertJsonPath('data.excluded.all_saturdays', 2)
            ->assertJsonPath('data.excluded.second_and_fourth_saturdays', 1)
            ->assertJsonPath('data.excluded.holidays', 1);
    }

    public function test_converted_lead_creates_project_and_client_and_lead_details_include_it(): void
    {
        $sales = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation' => 'Sales Executive']);
        $lead = Lead::create([
            'lead_code' => 'LD-1218',
            'source' => 'website',
            'full_name' => 'Messaging Contact',
            'company_name' => 'Messaging',
            'email' => 'client@example.com',
            'mobile_number' => '9930196353',
            'requirement' => 'Generator dealer web application.',
            'status' => 'converted',
            'assigned_to' => $sales->id,
            'location' => 'Ghatkopar West, Mumbai',
            'estimated_value' => 50000,
        ]);

        $created = $this->actingAs($sales)->postJson("/api/admin/leads/{$lead->id}/projects", [
            'name' => 'Messaging - Generator Dealer',
            'category' => 'Web Development',
            'priority' => 'Standard',
            'start_date' => '2026-10-23',
            'end_date' => '2026-11-22',
            'working_day' => 28,
            'deliverables' => 'Client requirement and agreed deliverables.',
            'special_instructions' => 'Weekly review every Monday.',
        ])->assertCreated()
            ->assertJsonPath('data.lead_id', $lead->id)
            ->assertJsonPath('data.client.name', 'Messaging')
            ->assertJsonPath('data.working_days', 28)
            ->assertJsonPath('data.working_day', 28)
            ->assertJsonPath('data.agreed_value', null)
            ->assertJsonCount(0, 'data.members');

        $projectId = $created->json('data.id');
        $this->assertDatabaseHas('clients', ['lead_id' => $lead->id, 'name' => 'Messaging']);
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'lead_id' => $lead->id,
            'working_days' => 28,
            'agreed_value' => null,
            'project_manager_id' => null,
        ]);

        $this->actingAs($sales)->getJson("/api/employee/leads/{$lead->id}")
            ->assertOk()->assertJsonPath('data.converted_project.id', $projectId);
        $this->actingAs($sales)->getJson("/api/admin/projects/{$projectId}")
            ->assertOk()->assertJsonPath('data.client.name', 'Messaging');
        $this->actingAs($sales)->postJson("/api/admin/leads/{$lead->id}/projects", [])->assertStatus(409);
    }

    public function test_client_crud_project_assignment_and_communication_crud_are_scoped(): void
    {
        Carbon::setTestNow('2026-10-23 11:00:00');
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $other = User::factory()->create(['role' => 'employee', 'status' => 'Active']);

        $created = $this->actingAs($employee)->postJson('/api/admin/clients', [
            'name' => 'Webcraft Solutions',
            'email' => 'sales@webcraft.com',
            'mobile_number' => '+91 67890 12345',
            'website' => 'https://webcraft.com',
            'address' => 'Mumbai',
            'status' => 'Active',
            'notes' => 'Preferred client',
        ])->assertCreated();
        $clientId = $created->json('data.id');

        $this->actingAs($employee)->getJson('/api/admin/clients')->assertOk()
            ->assertJsonPath('counts.all', 1)->assertJsonPath('data.0.id', $clientId);
        $this->actingAs($employee)->getJson('/api/admin/clients/search?query=Webcraft')->assertOk()
            ->assertJsonPath('total', 1);
        $this->actingAs($other)->getJson("/api/admin/clients/{$clientId}")->assertNotFound();

        $project = $this->actingAs($employee)->postJson("/api/admin/clients/{$clientId}/projects", [
            'name' => 'CRM Software Development',
            'description' => 'Develop a custom CRM.',
            'category' => 'Custom ERP / CRM',
            'start_date' => '2026-10-23',
            'end_date' => '2027-01-21',
            'estimated_value' => 550000,
            'status' => 'Active',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'In Progress')
            ->assertJsonPath('data.project_manager', null)
            ->assertJsonCount(0, 'data.team');

        $this->actingAs($employee)->getJson("/api/admin/clients/{$clientId}/projects?status=active")
            ->assertOk()->assertJsonPath('counts.active', 1)
            ->assertJsonPath('data.0.id', $project->json('data.id'));

        $communication = $this->actingAs($employee)->postJson("/api/admin/clients/{$clientId}/communications", [
            'type' => 'Email',
            'subject' => 'Project update',
            'communicated_at' => '2026-10-23 11:00:00',
            'recipient' => 'sales@webcraft.com',
            'description' => 'Shared the first delivery update.',
        ])->assertCreated();
        $communicationId = $communication->json('data.id');

        $this->actingAs($employee)->getJson("/api/admin/clients/{$clientId}/communications?type=Email")
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($employee)->getJson("/api/admin/clients/{$clientId}/communications/{$communicationId}")
            ->assertOk()->assertJsonPath('data.subject', 'Project update');
        $this->actingAs($employee)->patchJson("/api/admin/clients/{$clientId}/communications/{$communicationId}", [
            'subject' => 'Updated project status',
        ])->assertOk()->assertJsonPath('data.subject', 'Updated project status');
        $this->actingAs($employee)->deleteJson("/api/admin/clients/{$clientId}/communications/{$communicationId}")
            ->assertOk();

        $this->actingAs($employee)->patchJson("/api/admin/clients/{$clientId}", ['status' => 'Inactive'])
            ->assertOk()->assertJsonPath('data.status', 'Inactive');
        $this->actingAs($employee)->deleteJson("/api/admin/clients/{$clientId}")->assertStatus(409);

        $emptyClient = Client::create(['name' => 'Delete Me', 'status' => 'Active', 'created_by' => $employee->id]);
        $this->actingAs($employee)->deleteJson("/api/admin/clients/{$emptyClient->id}")->assertOk();
    }

    public function test_removed_project_creation_fields_are_rejected(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'Active']);
        $lead = Lead::create([
            'source' => 'website',
            'full_name' => 'Converted Contact',
            'status' => 'converted',
            'assigned_to' => $employee->id,
        ]);

        $this->actingAs($employee)->postJson("/api/admin/leads/{$lead->id}/projects", [
            'name' => 'Converted Project',
            'category' => 'Web Development',
            'start_date' => '2026-10-23',
            'end_date' => '2026-11-22',
            'working_day' => 28,
            'deliverables' => 'Project deliverables.',
            'project_manager_id' => $employee->id,
            'employee_ids' => [$employee->id],
            'agreed_value' => 50000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['project_manager_id', 'employee_ids', 'agreed_value']);

        $client = Client::create(['name' => 'Validation Client', 'status' => 'Active', 'created_by' => $employee->id]);
        $this->actingAs($employee)->postJson("/api/admin/clients/{$client->id}/projects", [
            'name' => 'Client Project',
            'description' => 'Project description.',
            'start_date' => '2026-10-23',
            'end_date' => '2026-11-22',
            'project_manager_id' => $employee->id,
            'employee_ids' => [$employee->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['project_manager_id', 'employee_ids']);
    }
}
