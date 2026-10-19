<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\WebsiteLeadSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_sync_maps_forms_and_does_not_duplicate_submissions(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'page' => 1,
                'per_page' => 50,
                'data' => [[
                    'submission' => [
                        'id' => '213',
                        'hash_id' => '5659f517-a36d-48c4-bd4d-6b3702246ed1',
                        'form_name' => 'New Form',
                        'referer' => 'https://digiemperor.com/contact/',
                        'created_at_gmt' => '2026-10-12 04:00:11',
                    ],
                    'fields' => [
                        'name' => 'Scott Gould',
                        'email' => 'SCOTT@example.com',
                        'field_93bc8cc' => str_repeat('A', 300),
                        'field_597bd0b' => '5613064740',
                        'field_d92d92f' => 'Social Media Services',
                        'message' => 'Please contact me.',
                    ],
                ]],
            ]),
        ]);

        $first = app(WebsiteLeadSyncService::class)->sync();
        $second = app(WebsiteLeadSyncService::class)->sync();

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, Lead::count());
        $this->assertDatabaseHas('leads', [
            'source' => 'website',
            'external_id' => '5659f517-a36d-48c4-bd4d-6b3702246ed1',
            'full_name' => 'Scott Gould',
            'company_name' => str_repeat('A', 255),
            'email' => 'scott@example.com',
            'mobile_number' => '5613064740',
            'requirement' => 'Social Media Services',
        ]);
        $this->assertSame(str_repeat('A', 300), Lead::first()->source_payload['fields']['field_93bc8cc']);
        $this->assertDatabaseCount('lead_histories', 1);
    }

    public function test_admin_can_list_search_assign_and_view_lead_history(): void
    {
        [$admin, $salesperson] = $this->users();
        $lead = Lead::create([
            'lead_code' => 'LD-1001',
            'source' => 'website',
            'external_id' => 'website-1',
            'full_name' => 'Anil Kumar',
            'company_name' => 'TechCorp India',
            'mobile_number' => '9876543210',
            'requirement' => 'ERP Attendance Suite',
        ]);
        $lead->histories()->create([
            'event_type' => 'captured',
            'title' => 'Lead captured via Website',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/api/admin/leads?source=website&assignment=unassigned')
            ->assertOk()
            ->assertJsonPath('summary.all', 1)
            ->assertJsonPath('summary.unassigned', 1)
            ->assertJsonPath('data.0.lead_code', 'LD-1001');
        $this->actingAs($admin)->getJson('/api/admin/leads/search?q=Attendance')
            ->assertOk()->assertJsonPath('pagination.total', 1);
        $this->actingAs($admin)->getJson('/api/admin/leads/sales-employees')
            ->assertOk()->assertJsonPath('data.0.id', $salesperson->id);
        $this->actingAs($admin)->postJson("/api/admin/leads/{$lead->id}/assign", [
            'employee_id' => $salesperson->id,
            'notes' => 'Assigned for follow-up.',
        ])->assertOk()->assertJsonPath('data.assigned_to', $salesperson->id);
        $this->actingAs($admin)->getJson("/api/admin/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.history')
            ->assertJsonPath('data.history.1.event_type', 'assigned');
    }

    public function test_admin_can_list_all_channels_without_source_filtering(): void
    {
        [$admin] = $this->users();
        Lead::create(['source' => 'website', 'external_id' => 'web-1', 'full_name' => 'Website Lead']);
        Lead::create(['source' => 'facebook', 'external_id' => 'fb-1', 'full_name' => 'Facebook Lead']);
        Lead::create(['source' => 'justdial', 'external_id' => 'jd-1', 'full_name' => 'Justdial Lead']);

        $this->actingAs($admin)->getJson('/api/admin/leads?page=1&per_page=20&source=all_channels')
            ->assertOk()
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonCount(3, 'data');

        $this->actingAs($admin)->getJson('/api/admin/leads?source=All%20Channels')
            ->assertOk()
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_admin_can_create_a_referral_lead(): void
    {
        [$admin, $salesperson] = $this->users();

        $response = $this->actingAs($admin)->postJson('/api/admin/leads', [
            'full_name' => 'Rohan Mehta',
            'company_name' => 'Mehta Enterprises',
            'email' => 'rohan@example.com',
            'mobile_number' => '+91 9876543210',
            'designation' => 'Proprietor',
            'lead_source' => 'Referral',
            'lead_status' => 'Interested',
            'assign_to' => $salesperson->id,
            'requirement' => 'Custom attendance application',
            'location' => 'Mumbai, Maharashtra',
            'estimated_value' => 250000,
            'expected_closing_date' => '2026-11-30',
            'notes' => 'Referred by an existing client.',
        ])->assertCreated()
            ->assertJsonPath('data.source', 'referral')
            ->assertJsonPath('data.status', 'interested')
            ->assertJsonPath('data.assigned_to', $salesperson->id);

        $leadId = $response->json('data.id');
        $this->assertDatabaseHas('leads', ['id' => $leadId, 'source' => 'referral']);
        $this->assertDatabaseCount('lead_histories', 2);
    }

    private function users(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $salesperson = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'employee_id' => 'EMP-SALES-1',
            'designation' => 'Sales Executive',
        ]);
        $salesRole = Role::create(['name' => 'Sales', 'status' => true]);
        $salesperson->roles()->attach($salesRole);

        return [$admin, $salesperson];
    }
}
