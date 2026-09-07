<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDesignationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_designation_apis_create_search_list_show_and_remove_employee(): void
    {
        $create = $this->actingAs($this->admin)->postJson('/api/admin/designations', [
            'name' => 'Senior Flutter Developer',
            'hierarchy_level' => 'senior',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Senior Flutter Developer')
            ->assertJsonPath('data.employees_count', 0);

        $designationId = $create->json('data.id');

        $employee = User::factory()->create([
            'role' => 'employee',
            'employee_id' => 'EMP-2026-003',
            'designation_id' => $designationId,
            'designation' => 'Senior Flutter Developer',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/designations')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employees_count', 1);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/designations/search?query=Flutter')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAs($this->admin)
            ->getJson("/api/admin/designations/{$designationId}")
            ->assertOk()
            ->assertJsonPath('data.employees.0.id', $employee->id)
            ->assertJsonPath('data.employees_count', 1);

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/designations/{$designationId}/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.employees_count', 0);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'designation_id' => null,
            'designation' => null,
        ]);
    }

    public function test_employee_apis_create_search_list_show_update_and_delete(): void
    {
        $designation = Designation::create([
            'name' => 'Senior Flutter Developer',
            'hierarchy_level' => 'senior',
        ]);

        $create = $this->actingAs($this->admin)->postJson('/api/admin/employees', [
            'employee_id' => 'EMP-2026-003',
            'name' => 'Rohit Sharma',
            'mobile_number' => '9876543210',
            'email' => 'rohit@example.com',
            'emergency_contact' => '9876500000',
            'address' => 'Pune, Maharashtra',
            'designation_id' => $designation->id,
            'monthly_salary' => 85000,
            'date_of_joining' => '2026-09-05',
            'skills' => ['Flutter', 'React Native'],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.designation_details.id', $designation->id)
            ->assertJsonPath('data.skills.0', 'Flutter');

        $employeeId = $create->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'role' => 'employee',
            'designation' => 'Senior Flutter Developer',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/employees')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/employees/search?query=Rohit')
            ->assertOk()
            ->assertJsonPath('data.0.id', $employeeId);

        $this->actingAs($this->admin)
            ->getJson("/api/admin/employees/{$employeeId}")
            ->assertOk()
            ->assertJsonPath('data.employee_id', 'EMP-2026-003');

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/employees/{$employeeId}", [
                'name' => 'Rohit S. Sharma',
                'monthly_salary' => 90000,
                'skills' => ['Flutter', 'PHP'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rohit S. Sharma')
            ->assertJsonPath('data.monthly_salary', '90000.00');

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/employees/{$employeeId}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $employeeId]);
    }

    public function test_employee_creation_cannot_override_employee_role(): void
    {
        $designation = Designation::create([
            'name' => 'PHP Developer',
            'hierarchy_level' => 'junior',
        ]);

        $payload = [
            'employee_id' => 'EMP-2026-004',
            'name' => 'Neha Gupta',
            'mobile_number' => '9876543211',
            'email' => 'neha@example.com',
            'emergency_contact' => '9876500001',
            'address' => 'Mumbai, Maharashtra',
            'designation_id' => $designation->id,
            'monthly_salary' => 65000,
            'date_of_joining' => '2026-09-05',
            'skills' => ['PHP'],
            'role' => 'admin',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/employees', $payload);

        $response->assertCreated()->assertJsonPath('data.role', 'employee');
        $this->assertDatabaseHas('users', ['email' => 'neha@example.com', 'role' => 'employee']);
    }
}
