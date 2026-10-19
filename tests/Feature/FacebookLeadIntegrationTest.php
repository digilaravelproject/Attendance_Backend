<?php

namespace Tests\Feature;

use App\FacebookLeadSyncService;
use App\Models\FacebookIntegration;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookLeadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_url_stores_the_flow_and_does_not_return_the_secret(): void
    {
        $response = $this->postJson('/api/facebook/authorization-url', [
            'client_id' => 'facebook-client-id',
            'client_secret' => 'facebook-client-secret',
            'redirect_uri' => 'https://api.example.com/api/facebook/callback',
            'state' => 'mobile-screen-42',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_state', 'mobile-screen-42')
            ->assertJsonMissing(['client_secret' => 'facebook-client-secret']);

        $integration = FacebookIntegration::firstOrFail();
        $this->assertSame($integration->state, $response->json('data.state'));
        $this->assertStringContainsString(urlencode($integration->state), $response->json('authorization_url'));
        $this->assertSame('facebook-client-secret', $integration->client_secret);
        $this->assertNotSame('facebook-client-secret', $integration->getRawOriginal('client_secret'));
        $this->assertSame($integration->id, session('facebook_oauth.integration_id'));
        $this->assertSame('https://attendance.digiemperor.com/api/facebook/callback', $integration->redirect_uri);
        $this->assertStringContainsString(
            urlencode('https://attendance.digiemperor.com/api/facebook/callback'),
            $response->json('authorization_url')
        );
    }

    public function test_callback_exchanges_code_selects_page_imports_lead_and_remains_idempotent(): void
    {
        config(['services.facebook.page_name' => 'Digi Emperor-Digital Marketing Agency']);
        $integration = FacebookIntegration::create([
            'state' => 'secure-oauth-state',
            'client_state' => 'mobile-screen-42',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://api.example.com/api/facebook/callback',
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/oauth/access_token')) {
                return Http::response(['access_token' => 'user-token', 'token_type' => 'bearer', 'expires_in' => 3600]);
            }
            if (str_contains($request->url(), '/me/accounts')) {
                return Http::response(['data' => [
                    ['id' => 'other-page', 'name' => 'Another Page', 'access_token' => 'other-token'],
                    ['id' => '109030488634930', 'name' => 'Digi Emperor-Digital Marketing Agency', 'access_token' => 'page-token'],
                ]]);
            }
            if (str_contains($request->url(), '/109030488634930/leadgen_forms')) {
                return Http::response(['data' => [[
                    'id' => '934843082662745',
                    'name' => 'Website Development',
                    'status' => 'ACTIVE',
                    'leads_count' => 1,
                ]]]);
            }

            return Http::response(['data' => [$this->facebookLeadPayload()]]);
        });

        $this->getJson('/api/facebook/callback?'.http_build_query([
            'code' => 'temporary-code',
            'state' => $integration->state,
        ]))->assertOk()
            ->assertJsonPath('data.page.id', '109030488634930')
            ->assertJsonPath('data.sync.created', 1);

        $integration->refresh();
        $this->assertSame('active', $integration->status);
        $this->assertSame('user-token', $integration->access_token);
        $this->assertSame('page-token', $integration->page_access_token);
        $this->assertNotSame('page-token', $integration->getRawOriginal('page_access_token'));
        $this->assertDatabaseHas('leads', [
            'source' => 'facebook',
            'external_id' => '1071795159020032',
            'full_name' => 'Test Person',
            'email' => 'test@meta.com',
            'mobile_number' => '+91 9876543210',
            'location' => 'Mumbai',
        ]);

        $lead = Lead::firstOrFail();
        $this->assertStringContainsString('What Type Of Website Do You Need: Ecommerce', $lead->requirement);
        $this->assertSame(1, $lead->histories()->count());

        $result = app(FacebookLeadSyncService::class)->sync($integration);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, Lead::count());
        $this->assertSame(1, $lead->histories()->count());
    }

    private function facebookLeadPayload(): array
    {
        return [
            'id' => '1071795159020032',
            'created_time' => '2026-10-14T09:49:53+0000',
            'form_id' => '934843082662745',
            'field_data' => [
                ['name' => 'what_type_of_website_do_you_need?', 'values' => ['Ecommerce']],
                ['name' => 'what_is_your_budget?', 'values' => ['₹1-2 lakh']],
                ['name' => 'full_name', 'values' => ['Test Person']],
                ['name' => 'phone_number', 'values' => ['+91 9876543210']],
                ['name' => 'city', 'values' => ['Mumbai']],
                ['name' => 'email', 'values' => ['TEST@META.COM']],
            ],
        ];
    }
}
