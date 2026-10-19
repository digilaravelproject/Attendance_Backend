<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLeadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_employee_can_list_search_and_view_only_assigned_leads(): void
    {
        [$salesOne, $salesTwo, $admin] = $this->users();
        $assigned = Lead::create([
            'lead_code' => 'TC-201',
            'source' => 'facebook',
            'full_name' => 'Rajesh Agrawal',
            'mobile_number' => '9823044556',
            'location' => 'Mumbai',
            'requirement' => '40 biometric devices',
            'status' => 'follow_up',
            'assigned_to' => $salesOne->id,
        ]);
        $assigned->histories()->create([
            'actor_id' => $admin->id,
            'event_type' => 'assigned',
            'title' => 'Assigned to Rahul Sharma',
            'occurred_at' => now(),
        ]);
        $other = Lead::create([
            'lead_code' => 'TC-202',
            'source' => 'referral',
            'full_name' => 'Other Customer',
            'mobile_number' => '9823044557',
            'status' => 'new',
            'assigned_to' => $salesTwo->id,
        ]);

        $this->actingAs($salesOne)->getJson('/api/employee/leads')
            ->assertOk()
            ->assertJsonPath('summary.all', 1)
            ->assertJsonPath('summary.follow_up', 1)
            ->assertJsonPath('data.0.id', $assigned->id)
            ->assertJsonPath('data.0.assignment_origin', 'admin_assigned');
        $this->actingAs($salesOne)->getJson('/api/employee/leads/search?q=biometric')
            ->assertOk()->assertJsonPath('pagination.total', 1);
        $this->actingAs($salesOne)->getJson('/api/employee/leads/search?q=Other')
            ->assertOk()->assertJsonPath('pagination.total', 0);
        $this->actingAs($salesOne)->getJson("/api/employee/leads/{$assigned->id}")
            ->assertOk()->assertJsonPath('data.lead_code', 'TC-201');
        $this->actingAs($salesOne)->getJson("/api/employee/leads/{$other->id}")
            ->assertNotFound();
    }

    public function test_sales_employee_can_create_update_and_reassign_a_lead(): void
    {
        [$salesOne, $salesTwo] = $this->users();

        $created = $this->actingAs($salesOne)->postJson('/api/employee/leads', [
            'full_name' => 'Rohan Mehta',
            'company_name' => 'Mehta Enterprises',
            'email' => 'rohan@example.com',
            'mobile_number' => '+91 9876543210',
            'designation' => 'Proprietor',
            'lead_source' => 'Referral',
            'lead_status' => 'Interested',
            'assign_to' => $salesOne->id,
            'requirement' => 'Attendance application',
            'location' => 'Mumbai',
            'estimated_value' => 250000,
            'expected_closing_date' => '2026-11-30',
            'notes' => 'Self brought referral lead.',
        ])->assertCreated()
            ->assertJsonPath('data.assignment_origin', 'self_brought')
            ->assertJsonPath('data.created_by.id', $salesOne->id);
        $leadId = $created->json('data.id');

        $this->actingAs($salesOne)->patchJson("/api/employee/leads/{$leadId}/status", [
            'status' => 'Follow-up',
            'call_note' => 'Client requested a product demo tomorrow.',
        ])->assertOk()->assertJsonPath('data.status', 'follow_up');

        $this->actingAs($salesOne)->getJson('/api/employee/leads/sales-employees?q=Sales')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($salesOne)->postJson("/api/employee/leads/{$leadId}/assign", [
            'employee_id' => $salesTwo->id,
            'reason' => 'workload_balancing',
            'notes' => 'Assigned for follow-up and client meeting.',
        ])->assertOk()
            ->assertJsonPath('data.assigned_to', $salesTwo->id)
            ->assertJsonPath('data.is_owner', false);

        $this->actingAs($salesOne)->getJson("/api/employee/leads/{$leadId}")->assertNotFound();
        $this->actingAs($salesTwo)->getJson("/api/employee/leads/{$leadId}")
            ->assertOk()
            ->assertJsonCount(4, 'data.history')
            ->assertJsonPath('data.history.2.event_type', 'status_changed')
            ->assertJsonPath('data.history.3.metadata.reason', 'workload_balancing');
    }

    public function test_non_sales_employee_cannot_use_employee_leads_module(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'designation' => 'PHP Developer',
        ]);

        $this->actingAs($employee)->getJson('/api/employee/leads')->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. An active sales employee is required.');
    }

    private function users(): array
    {
        $salesRole = Role::firstOrCreate(['name' => 'Sales'], ['status' => true]);
        $salesOne = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'employee_id' => 'EMP-SALES-1',
            'name' => 'Rahul Sharma',
            'designation' => 'Sales Executive',
        ]);
        $salesTwo = User::factory()->create([
            'role' => 'employee',
            'status' => 'Active',
            'employee_id' => 'EMP-SALES-2',
            'name' => 'Neha Verma',
            'designation' => 'Senior Sales Executive',
        ]);
        $salesOne->roles()->attach($salesRole);
        $salesTwo->roles()->attach($salesRole);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);

        return [$salesOne, $salesTwo, $admin];
    }
}
