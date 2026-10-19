<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookIntegration;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadIntegrationStatusController extends Controller
{
    public function show(): JsonResponse
    {
        $facebook = FacebookIntegration::query()
            ->where('status', 'active')
            ->whereNotNull('page_id')
            ->whereNotNull('page_access_token')
            ->latest('id')
            ->first() ?? FacebookIntegration::query()->latest('id')->first();
        $facebookConnected = $facebook?->status === 'active'
            && filled($facebook->page_id)
            && filled($facebook->page_access_token)
            && (! $facebook->token_expires_at || $facebook->token_expires_at->isFuture());

        return response()->json([
            'status' => true,
            'message' => 'Lead integration connection status retrieved successfully.',
            'data' => [
                'facebook' => [
                    'connected' => $facebookConnected,
                    'status' => $facebookConnected
                        ? 'connected'
                        : (($facebook?->token_expires_at?->isPast()) ? 'token_expired' : ($facebook?->status ?? 'not_configured')),
                    'page_id' => $facebook?->page_id,
                    'page_name' => $facebook?->page_name,
                    'authorized_at' => $facebook?->authorized_at?->toIso8601String(),
                    'last_synced_at' => $facebook?->last_synced_at?->toIso8601String(),
                    'last_error' => $facebook?->last_error,
                    'imported_leads_count' => Lead::whereIn('source', ['facebook', 'facebook_ads'])->count(),
                ],
                'google' => [
                    'connected' => false,
                    'status' => 'not_configured',
                    'message' => 'Google Ads OAuth integration is not configured in this backend.',
                    'imported_leads_count' => Lead::where('source', 'google_ads')->count(),
                ],
            ],
        ]);
    }
}
