<?php

namespace App;

use App\Models\FacebookIntegration;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FacebookLeadSyncService
{
    public function exchangeCode(FacebookIntegration $integration, string $code): array
    {
        $response = $this->client()->get($this->graphUrl('oauth/access_token'), [
            'client_id' => $integration->client_id,
            'client_secret' => $integration->client_secret,
            'redirect_uri' => $integration->redirect_uri,
            'code' => $code,
        ])->throw()->json();

        if (! is_array($response) || empty($response['access_token'])) {
            throw new RuntimeException('Facebook did not return an access token.');
        }

        $integration->update([
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'bearer',
            'token_expires_at' => isset($response['expires_in']) ? now()->addSeconds((int) $response['expires_in']) : null,
            'status' => 'authorized',
            'authorized_at' => now(),
            'last_error' => null,
        ]);

        return $response;
    }

    public function selectConfiguredPage(FacebookIntegration $integration): FacebookIntegration
    {
        if (! $integration->access_token) {
            throw new RuntimeException('The Facebook integration has not been authorized.');
        }

        $pages = $this->connection('me/accounts', $integration->access_token, [
            'fields' => 'id,name,access_token',
            'limit' => 100,
        ]);
        $targetName = (string) config('services.facebook.page_name');
        $page = collect($pages)->first(
            fn (array $item) => Str::lower(trim((string) ($item['name'] ?? ''))) === Str::lower(trim($targetName))
        );

        if (! $page || empty($page['id']) || empty($page['access_token'])) {
            throw new RuntimeException("Facebook page [{$targetName}] was not found or has no page access token.");
        }

        FacebookIntegration::query()
            ->whereKeyNot($integration->id)
            ->where('client_id', $integration->client_id)
            ->where('page_id', (string) $page['id'])
            ->whereIn('status', ['active', 'sync_failed'])
            ->update(['status' => 'superseded']);

        $integration->update([
            'page_id' => (string) $page['id'],
            'page_name' => (string) $page['name'],
            'page_access_token' => (string) $page['access_token'],
            'status' => 'active',
            'last_error' => null,
        ]);

        return $integration->fresh();
    }

    public function sync(FacebookIntegration $integration): array
    {
        if (! $integration->page_id || ! $integration->page_access_token) {
            $integration = $this->selectConfiguredPage($integration);
        }

        $created = 0;
        $updated = 0;
        $processed = 0;

        try {
            $forms = $this->connection("{$integration->page_id}/leadgen_forms", $integration->page_access_token, [
                'fields' => 'id,name,status,leads_count',
                'limit' => 100,
            ]);

            foreach ($forms as $form) {
                if (empty($form['id'])) {
                    continue;
                }

                $leads = $this->connection("{$form['id']}/leads", $integration->page_access_token, [
                    'fields' => 'id,created_time,field_data,form_id,ad_id',
                    'limit' => 100,
                ]);

                foreach ($leads as $payload) {
                    if (! is_array($payload) || empty($payload['id'])) {
                        continue;
                    }

                    $isNew = $this->store($payload, $form, $integration);
                    $processed++;
                    $isNew ? $created++ : $updated++;
                }
            }

            $integration->update(['status' => 'active', 'last_synced_at' => now(), 'last_error' => null]);

            return compact('processed', 'created', 'updated');
        } catch (Throwable $exception) {
            $integration->update([
                'status' => 'sync_failed',
                'last_error' => Str::limit($exception->getMessage(), 60000, ''),
            ]);
            throw $exception;
        }
    }

    private function connection(string $path, string $token, array $query): array
    {
        $items = [];
        $url = $this->graphUrl($path);
        $query['access_token'] = $token;
        $pages = 0;
        $maxPages = (int) config('services.facebook.max_pages', 100);

        do {
            $response = $this->client()->get($url, $query)->throw()->json();
            if (! is_array($response) || ! is_array($response['data'] ?? null)) {
                throw new RuntimeException("Facebook Graph endpoint [{$path}] returned an invalid response.");
            }

            array_push($items, ...$response['data']);
            $url = is_string($response['paging']['next'] ?? null) ? $response['paging']['next'] : null;
            $query = [];
            $pages++;
        } while ($url && $pages < $maxPages);

        return $items;
    }

    private function store(array $payload, array $form, FacebookIntegration $integration): bool
    {
        $fields = $this->fields($payload['field_data'] ?? []);
        $externalId = (string) $payload['id'];

        return DB::transaction(function () use ($payload, $form, $integration, $fields, $externalId) {
            $lead = Lead::where('source', 'facebook')->where('external_id', $externalId)->first();
            $isNew = ! $lead;
            $lead ??= new Lead(['source' => 'facebook', 'external_id' => $externalId]);

            $lead->fill([
                'full_name' => $this->value($fields, ['full_name', 'name']) ?: 'Facebook Lead',
                'company_name' => $this->value($fields, ['company_name', 'company']),
                'email' => $this->email($this->value($fields, ['email'])),
                'mobile_number' => $this->value($fields, ['phone_number', 'phone', 'mobile_number']),
                'location' => $this->value($fields, ['city', 'location']),
                'requirement' => $this->requirements($fields),
                'source_payload' => [
                    'page_id' => $integration->page_id,
                    'page_name' => $integration->page_name,
                    'form' => $form,
                    'lead' => $payload,
                ],
                'source_created_at' => $this->date($payload['created_time'] ?? null),
                'last_synced_at' => now(),
            ]);
            $lead->save();

            if ($isNew) {
                $lead->update(['lead_code' => 'LD-'.str_pad((string) (1000 + $lead->id), 4, '0', STR_PAD_LEFT)]);
                $lead->histories()->create([
                    'event_type' => 'captured',
                    'title' => 'Lead captured via Facebook Ads',
                    'description' => 'Inbound lead imported from Facebook form '.($form['name'] ?? $form['id'] ?? '').'.',
                    'metadata' => [
                        'facebook_lead_id' => $externalId,
                        'form_id' => $payload['form_id'] ?? $form['id'] ?? null,
                        'page_id' => $integration->page_id,
                    ],
                    'occurred_at' => $lead->source_created_at ?? now(),
                ]);
            }

            return $isNew;
        });
    }

    private function fields(mixed $fieldData): array
    {
        if (! is_array($fieldData)) {
            return [];
        }

        $fields = [];
        foreach ($fieldData as $field) {
            if (! is_array($field) || ! isset($field['name'])) {
                continue;
            }
            $values = is_array($field['values'] ?? null) ? $field['values'] : [];
            $fields[(string) $field['name']] = implode(', ', array_map('strval', $values));
        }

        return $fields;
    }

    private function value(array $fields, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                return Str::limit($value, 255, '');
            }
        }

        return null;
    }

    private function requirements(array $fields): ?string
    {
        $excluded = ['full_name', 'name', 'company_name', 'company', 'email', 'phone_number', 'phone', 'mobile_number', 'city', 'location'];
        $lines = [];
        foreach ($fields as $name => $value) {
            if (! in_array($name, $excluded, true) && trim((string) $value) !== '') {
                $label = Str::headline(trim((string) $name, "_? \t\n\r\0\x0B"));
                $lines[] = $label.': '.trim((string) $value);
            }
        }

        return $lines ? Str::limit(implode(PHP_EOL, $lines), 60000, '') : null;
    }

    private function email(?string $email): ?string
    {
        return $email ? Str::lower(trim($email)) : null;
    }

    private function date(mixed $date): ?Carbon
    {
        return is_string($date) && trim($date) !== '' ? Carbon::parse($date)->utc() : null;
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.facebook.timeout', 30))
            ->retry(3, 500);
    }

    private function graphUrl(string $path): string
    {
        return rtrim((string) config('services.facebook.graph_url'), '/').'/'.ltrim($path, '/');
    }
}
