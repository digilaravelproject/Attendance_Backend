<?php

namespace Tests\Feature;

use App\Models\FacebookIntegration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_lead_statuses_and_counts_are_returned(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);

        $this->actingAs($admin)->getJson('/api/admin/lead-statuses')
            ->assertOk()
            ->assertJsonPath('total', 11)
            ->assertJsonPath('data.0.slug', 'new');

        $created = $this->actingAs($admin)->postJson('/api/admin/lead-statuses', [
            'status_name' => 'In Progress',
            'category' => 'Active Lead (In negotiation)',
            'guideline' => 'Technical evaluation or contract review is in progress.',
            'tag_color' => '#6366F1',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'in_progress')
            ->assertJsonPath('data.category', 'active_lead');

        $statusId = $created->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/lead-statuses/total')
            ->assertOk()->assertJsonPath('data.total_statuses', 12);
        $this->actingAs($admin)->getJson("/api/admin/lead-statuses/{$statusId}")
            ->assertOk()->assertJsonPath('data.name', 'In Progress');
        $this->actingAs($admin)->patchJson("/api/admin/lead-statuses/{$statusId}", [
            'name' => 'Negotiation',
            'badge_color' => '#8B5CF6',
        ])->assertOk()
            ->assertJsonPath('data.slug', 'negotiation')
            ->assertJsonPath('data.badge_color', '#8B5CF6');

        $this->actingAs($admin)->deleteJson("/api/admin/lead-statuses/{$statusId}")
            ->assertOk();
        $this->assertDatabaseMissing('lead_statuses', ['id' => $statusId]);
    }

    public function test_custom_status_is_accepted_by_lead_api_and_delete_can_replace_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        $salesperson = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'designation' => 'Sales Executive',
        ]);
        $salesperson->roles()->attach(Role::create(['name' => 'Sales', 'status' => true]));
        $custom = LeadStatus::create([
            'name' => 'Demo Booked',
            'slug' => 'demo_booked',
            'category' => 'active_lead',
            'badge_color' => '#10B981',
            'order_position' => 50,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/leads', [
            'full_name' => 'Demo Customer',
            'mobile_number' => '9876543210',
            'source' => 'referral',
            'status' => 'demo_booked',
            'assigned_to' => $salesperson->id,
        ])->assertCreated()->assertJsonPath('data.status', 'demo_booked');

        $leadId = $response->json('data.id');
        $this->actingAs($admin)->deleteJson("/api/admin/lead-statuses/{$custom->id}")
            ->assertStatus(409)->assertJsonPath('leads_count', 1);

        $replacement = LeadStatus::where('slug', 'interested')->firstOrFail();
        $this->actingAs($admin)->deleteJson("/api/admin/lead-statuses/{$custom->id}", [
            'replacement_status_id' => $replacement->id,
        ])->assertOk();
        $this->assertDatabaseHas('leads', ['id' => $leadId, 'status' => 'interested']);
    }

    public function test_admin_can_get_facebook_and_google_connection_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        FacebookIntegration::create([
            'state' => 'connected-state',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://attendance.digiemperor.com/api/facebook/callback',
            'page_id' => 'page-123',
            'page_name' => 'Digi Emperor',
            'page_access_token' => 'page-token',
            'status' => 'active',
            'authorized_at' => now(),
        ]);
        Lead::create([
            'source' => 'google',
            'external_id' => 'google-1',
            'full_name' => 'Google Lead',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/lead-integrations/status')
            ->assertOk()
            ->assertJsonPath('data.facebook.connected', true)
            ->assertJsonPath('data.facebook.page_id', 'page-123')
            ->assertJsonPath('data.google.connected', false)
            ->assertJsonPath('data.google.status', 'not_configured')
            ->assertJsonPath('data.google.imported_leads_count', 1);
    }
}
