<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHolidayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_holidays_and_employee_reads_database_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);

        $created = $this->actingAs($admin)->postJson('/api/admin/holidays', [
            'name' => 'Republic Day',
            'date' => '2027-01-26',
            'type' => 'National Holiday',
            'location' => 'All Locations',
            'repeat_every_year' => true,
            'description' => 'Constitution of India observance.',
        ])->assertCreated()
            ->assertJsonPath('data.date', '2027-01-26')
            ->assertJsonPath('data.day', 'Tue')
            ->assertJsonPath('data.day_name', 'Tuesday')
            ->assertJsonPath('data.type', 'National')
            ->assertJsonPath('data.repeat_every_year', true);
        $id = $created->json('data.id');

        $this->actingAs($employee)->getJson('/api/admin/holidays?year=2027')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.national', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.date', '2027-01-26');

        $this->actingAs($admin)->getJson("/api/admin/holidays/{$id}")
            ->assertOk()->assertJsonPath('data.name', 'Republic Day');

        $this->actingAs($admin)->patchJson("/api/admin/holidays/{$id}", [
            'name' => 'Republic Day Updated',
            'date' => '2027-01-27',
            'type' => 'Restricted Holiday',
            'location' => 'Pune',
            'repeat_every_year' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Republic Day Updated')
            ->assertJsonPath('data.date', '2027-01-27')
            ->assertJsonPath('data.type', 'Restricted')
            ->assertJsonPath('data.location', 'Pune');

        $this->actingAs($admin)->deleteJson("/api/admin/holidays/{$id}")
            ->assertOk()->assertJsonPath('status', true);
        $this->assertDatabaseMissing('holidays', ['id' => $id]);
    }

    public function test_only_admin_can_use_holiday_management_routes(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $holiday = Holiday::create([
            'name' => 'Founders Day', 'date' => '2027-11-10', 'type' => 'Optional',
        ]);

        $this->actingAs($employee)->getJson("/api/admin/holidays/{$holiday->id}")->assertForbidden();
        $this->actingAs($employee)->patchJson("/api/admin/holidays/{$holiday->id}", ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($employee)->deleteJson("/api/admin/holidays/{$holiday->id}")->assertForbidden();
    }

    public function test_holiday_requires_a_date_and_rejects_duplicate_name_and_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/admin/holidays', [
            'name' => 'No Date', 'type' => 'National',
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        Holiday::create(['name' => 'Republic Day', 'date' => '2027-01-26', 'type' => 'National']);
        $this->actingAs($admin)->postJson('/api/admin/holidays', [
            'name' => 'Republic Day', 'date' => '2027-01-26', 'type' => 'National',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
