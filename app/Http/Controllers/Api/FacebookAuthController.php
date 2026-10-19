<?php

namespace App\Http\Controllers\Api;

use App\FacebookLeadSyncService;
use App\Http\Controllers\Controller;
use App\Models\FacebookIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class FacebookAuthController extends Controller
{
    public function generateAuthorizationUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // Meta must return to the backend so it can exchange the code and import leads.
        // Do not allow Postman or a mobile deep link to bypass this callback.
        $redirectUri = (string) config('services.facebook.redirect_uri');

        // Always use an unguessable OAuth state. The caller's state is retained separately.
        $state = Str::random(64);
        $integration = FacebookIntegration::create([
            'state' => $state,
            'client_state' => $validated['state'] ?? null,
            'client_id' => $validated['client_id'],
            'client_secret' => $validated['client_secret'],
            'redirect_uri' => $redirectUri,
        ]);

        $queryParams = [
            'client_id' => $validated['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', config('services.facebook.scopes')),
            'state' => $state,
        ];

        $authorizationUrl = rtrim((string) config('services.facebook.authorization_url'), '/').'?'.http_build_query(
            $queryParams,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $request->session()->put('facebook_oauth', [
            'integration_id' => $integration->id,
            'state' => $state,
            'client_state' => $integration->client_state,
            'client_id' => $integration->client_id,
            'client_secret' => Crypt::encryptString($validated['client_secret']),
            'redirect_uri' => $integration->redirect_uri,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Facebook authorization URL generated successfully.',
            'data' => [
                'integration_id' => $integration->id,
                'state' => $state,
                'client_state' => $integration->client_state,
                'redirect_uri' => $integration->redirect_uri,
            ],
            'authorization_url' => $authorizationUrl,
        ], 200);
    }

    public function callback(Request $request, FacebookLeadSyncService $service): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required_without:error', 'string', 'max:4096'],
            'state' => ['required', 'string', 'max:128'],
            'error' => ['sometimes', 'nullable', 'string', 'max:255'],
            'error_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $integration = FacebookIntegration::where('state', $validated['state'])->first();

        if (! $integration) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OAuth state.'], 422);
        }
        if (! empty($validated['error'])) {
            $message = $validated['error_description'] ?? $validated['error'];
            $integration->update(['status' => 'authorization_failed', 'last_error' => $message]);

            return response()->json(['success' => false, 'message' => $message], 422);
        }

        try {
            $token = $service->exchangeCode($integration, $validated['code']);
            $request->session()->put('facebook_oauth.access_token', Crypt::encryptString($token['access_token']));
            $integration = $service->selectConfiguredPage($integration->fresh());
            $result = $service->sync($integration);

            return response()->json([
                'success' => true,
                'message' => 'Facebook account authorized and leads synchronized successfully.',
                'data' => [
                    'integration_id' => $integration->id,
                    'client_state' => $integration->client_state,
                    'page' => ['id' => $integration->page_id, 'name' => $integration->page_name],
                    'sync' => $result,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $integration->refresh();
            $integration->update([
                'status' => $integration->page_access_token ? 'sync_failed' : 'authorization_failed',
                'last_error' => Str::limit($exception->getMessage(), 60000, ''),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Facebook authorization or lead synchronization failed.',
            ], 502);
        }
    }
}
