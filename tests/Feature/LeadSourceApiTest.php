<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use App\Services\WebsiteLeadSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadSourceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_non_admin_can_manage_source_rules_and_inbound_leads_are_round_robin_assigned(): void
    {
        $requester = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation' => 'Developer']);
        [$salesOne, $salesTwo] = $this->salesEmployees();

        $created = $this->actingAs($requester)->postJson('/api/admin/lead-sources', [
            'lead_source_name' => 'JustDial',
            'allocation_strategy' => 'Round-Robin',
            'assigned_employee_ids' => [$salesOne->id, $salesTwo->id],
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.source_key', 'justdial')
            ->assertJsonPath('data.allocation_strategy', 'round_robin')
            ->assertJsonCount(2, 'data.employees');
        $sourceId = $created->json('data.id');

        $this->actingAs($requester)->getJson('/api/admin/lead-sources')
            ->assertOk()->assertJsonPath('summary.active', 5);
        $this->actingAs($requester)->getJson("/api/admin/lead-sources/{$sourceId}")
            ->assertOk()->assertJsonPath('data.name', 'JustDial');

        $this->postJson('/api/justdial_leads', $this->payload('JD-1', 'First Lead'))
            ->assertCreated()->assertJsonPath('data.assigned_to', $salesOne->id);
        $this->postJson('/api/justdial_leads', $this->payload('JD-2', 'Second Lead'))
            ->assertCreated()->assertJsonPath('data.assigned_to', $salesTwo->id);

        $this->actingAs($requester)->patchJson("/api/admin/lead-sources/{$sourceId}", [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->postJson('/api/justdial_leads', $this->payload('JD-3', 'Blocked Lead'))
            ->assertStatus(409)
            ->assertJsonPath('is_active', false);
        $this->assertDatabaseMissing('leads', ['external_id' => 'JD-3']);
    }

    public function test_sales_employee_detail_is_available_without_admin_role_restriction(): void
    {
        $requester = User::factory()->create(['role' => 'employee', 'status' => 'Active', 'designation' => 'Developer']);
        [$sales] = $this->salesEmployees();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $adminLead = Lead::create([
            'source' => 'facebook', 'full_name' => 'Admin Lead', 'status' => 'converted',
            'assigned_to' => $sales->id, 'created_by' => $admin->id, 'estimated_value' => 50000,
        ]);
        $adminLead->histories()->create([
            'event_type' => 'assigned', 'title' => 'Assigned', 'occurred_at' => now(),
        ]);
        Lead::create([
            'source' => 'referral', 'full_name' => 'Self Lead', 'status' => 'new',
            'assigned_to' => $sales->id, 'created_by' => $sales->id,
        ]);
        $sales->update(['sales_target' => 100000]);

        $this->actingAs($requester)->getJson("/api/admin/leads/sales-employees/{$sales->id}")
            ->assertOk()
            ->assertJsonPath('data.employee.id', $sales->id)
            ->assertJsonPath('data.summary.revenue', 50000)
            ->assertJsonPath('data.summary.target_achieved_percentage', 50)
            ->assertJsonPath('data.summary.admin_assigned_leads', 1)
            ->assertJsonPath('data.summary.self_brought_leads', 1)
            ->assertJsonCount(2, 'data.leads');
    }

    public function test_inactive_website_source_skips_remote_fetch_and_database_insert(): void
    {
        LeadSource::where('source_key', 'website')->update(['is_active' => false]);
        Http::fake();

        $result = app(WebsiteLeadSyncService::class)->sync();

        $this->assertSame([
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped_inactive' => true,
        ], $result);
        Http::assertNothingSent();
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_manual_lead_uses_source_assignment_when_employee_is_not_supplied(): void
    {
        $requester = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        [$sales] = $this->salesEmployees();
        $source = LeadSource::where('source_key', 'referral')->firstOrFail();
        $source->update([
            'allocation_strategy' => 'primary',
            'is_active' => true,
            'is_configured' => true,
        ]);
        $source->employees()->sync([$sales->id => ['position' => 0]]);

        $this->actingAs($requester)->postJson('/api/admin/leads', [
            'full_name' => 'Automatically Assigned Lead',
            'mobile_number' => '9876543210',
            'source' => 'referral',
            'status' => 'new',
        ])->assertCreated()
            ->assertJsonPath('data.assigned_to', $sales->id);

        $this->assertDatabaseHas('lead_histories', [
            'lead_id' => Lead::firstOrFail()->id,
            'event_type' => 'auto_assigned',
        ]);
    }

    public function test_inactive_source_blocks_manual_lead_insertion(): void
    {
        $requester = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        LeadSource::where('source_key', 'referral')->update(['is_active' => false]);

        $this->actingAs($requester)->postJson('/api/admin/leads', [
            'full_name' => 'Blocked Manual Lead',
            'mobile_number' => '9876543210',
            'source' => 'referral',
            'status' => 'new',
        ])->assertStatus(409)
            ->assertJsonPath('source_key', 'referral')
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseCount('leads', 0);
    }

    private function salesEmployees(): array
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['status' => true]);
        $first = User::factory()->create([
            'role' => 'employee', 'status' => 'Active', 'name' => 'Rahul Sharma', 'designation' => 'Sales Executive',
        ]);
        $second = User::factory()->create([
            'role' => 'employee', 'status' => 'Active', 'name' => 'Neha Singh', 'designation' => 'Sales Executive',
        ]);
        $first->roles()->attach($role);
        $second->roles()->attach($role);

        return [$first, $second];
    }

    private function payload(string $id, string $name): array
    {
        return [
            'leadid' => $id,
            'name' => $name,
            'mobile' => '9876543210',
            'date' => '2026-10-22',
            'time' => '13:10:11',
        ];
    }
}
