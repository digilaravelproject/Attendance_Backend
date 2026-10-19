<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JustdialLeadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_creates_and_idempotently_updates_a_justdial_lead(): void
    {
        $payload = $this->payload();

        $this->postJson('/api/justdial_leads', $payload)
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.source', 'justdial')
            ->assertJsonPath('data.external_id', 'JDF99CB5961B40')
            ->assertJsonPath('data.full_name', 'ABHAY SHAH')
            ->assertJsonPath('data.location', 'Ghatkopar West, Mumbai, Apollo Bunder');

        $this->assertDatabaseHas('leads', [
            'source' => 'justdial',
            'external_id' => 'JDF99CB5961B40',
            'company_name' => 'Greaves Cotton Ltd',
            'mobile_number' => '9XXXXX',
            'requirement' => 'Generator Dealers',
        ]);
        $this->assertDatabaseCount('lead_histories', 1);

        $payload['mobile'] = '9888888888';
        $this->postJson('/api/justdial_leads', $payload)
            ->assertOk()
            ->assertJsonPath('data.mobile_number', '9888888888');

        $this->assertSame(1, Lead::where('source', 'justdial')->count());
        $this->assertDatabaseCount('lead_histories', 1);
    }

    public function test_public_api_validates_required_fields(): void
    {
        $this->postJson('/api/justdial_leads', ['name' => 'Missing ID'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('leadid');
    }

    private function payload(): array
    {
        return [
            'leadid' => 'JDF99CB5961B40',
            'leadtype' => 'category',
            'prefix' => '',
            'name' => "ABHAY\nSHAH",
            'mobile' => '9XXXXX',
            'phone' => '',
            'email' => '',
            'date' => '2020-12-24',
            'category' => "Generator\nDealers",
            'area' => 'Ghatkopar West',
            'city' => 'Mumbai',
            'brancharea' => 'Apollo Bunder',
            'dncmobile' => 0,
            'dncphone' => 0,
            'company' => "Greaves Cotton\nLtd",
            'pincode' => '0',
            'time' => '13:10:11',
            'branchpin' => '400001',
            'parentid' => 'PXX22.XX22.150705230454.M4B1',
        ];
    }
}
