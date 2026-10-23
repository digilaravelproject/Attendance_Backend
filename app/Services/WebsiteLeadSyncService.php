<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WebsiteLeadSyncService
{
    public function __construct(private readonly LeadSourceAssignmentService $leadSources) {}

    private const FIELD_MAP = [
        'name' => ['name', 'full_name', 'field_e46caed', 'field_05f2c38'],
        'company' => ['company', 'company_name', 'field_93bc8cc', 'field_01b4e0d'],
        'email' => ['email', 'email_send_proposal', 'email_send_proposal_2'],
        'phone' => ['phone', 'mobile', 'mobile_number', 'field_597bd0b', 'field_76fe188', 'field_757edf4', 'field_59fe67f'],
        'requirement' => ['requirement', 'service', 'services', 'field_d92d92f', 'field_617b709', 'field_88434f9', 'field_0c8911d'],
        'notes' => ['message', 'notes', 'field_0e4bb2e'],
    ];

    public function sync(): array
    {
        if (! $this->leadSources->isActive('website')) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped_inactive' => true];
        }

        $url = config('services.website_leads.url');
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('WEBSITE_LEADS_URL is not configured.');
        }

        $created = 0;
        $updated = 0;
        $processed = 0;
        $page = 1;
        $perPage = (int) config('services.website_leads.per_page', 50);
        $maxPages = (int) config('services.website_leads.max_pages', 100);

        do {
            $payload = $this->client()->get($url, ['page' => $page, 'per_page' => $perPage])->throw()->json();
            if (! is_array($payload) || ($payload['success'] ?? true) === false) {
                throw new RuntimeException('The website leads endpoint returned an invalid response.');
            }

            $items = Arr::get($payload, 'data', []);
            if (! is_array($items)) {
                throw new RuntimeException('The website leads endpoint data field must be an array.');
            }

            foreach ($items as $item) {
                if (! is_array($item) || ! is_array($item['submission'] ?? null)) {
                    continue;
                }

                $wasCreated = $this->store($item);
                $processed++;
                $wasCreated ? $created++ : $updated++;
            }

            $reportedPerPage = max(1, (int) ($payload['per_page'] ?? $perPage));
            $page++;
        } while (count($items) >= $reportedPerPage && $page <= $maxPages);

        return compact('processed', 'created', 'updated');
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.website_leads.timeout', 30))
            ->retry(3, 500);
    }

    private function store(array $item): bool
    {
        $submission = $item['submission'];
        $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
        $externalId = (string) ($submission['hash_id'] ?? $submission['id'] ?? '');

        if ($externalId === '') {
            throw new RuntimeException('A website lead is missing both hash_id and submission id.');
        }

        return DB::transaction(function () use ($submission, $fields, $item, $externalId) {
            $lead = Lead::where('source', 'website')->where('external_id', $externalId)->first();
            $isNew = ! $lead;
            $lead ??= new Lead(['source' => 'website', 'external_id' => $externalId]);

            $lead->fill([
                'full_name' => $this->limitedField($fields, 'name', 255) ?: 'Website Lead',
                'company_name' => $this->limitedField($fields, 'company', 255),
                'email' => $this->normalizeEmail($this->limitedField($fields, 'email', 255)),
                'mobile_number' => $this->limitedField($fields, 'phone', 40),
                'requirement' => $this->limitedField($fields, 'requirement', 60000),
                'notes' => $this->limitedField($fields, 'notes', 60000),
                'source_payload' => $item,
                'source_created_at' => $this->date($submission['created_at_gmt'] ?? $submission['created_at'] ?? null),
                'last_synced_at' => now(),
            ]);
            $lead->save();

            if ($isNew) {
                $lead->update(['lead_code' => 'LD-'.str_pad((string) (1000 + $lead->id), 4, '0', STR_PAD_LEFT)]);
                $lead->histories()->create([
                    'event_type' => 'captured',
                    'title' => 'Lead captured via Website',
                    'description' => 'Inbound inquiry imported from the website lead form.',
                    'metadata' => [
                        'submission_id' => $submission['id'] ?? null,
                        'form_name' => $submission['form_name'] ?? null,
                        'referer' => $submission['referer'] ?? null,
                    ],
                    'occurred_at' => $lead->source_created_at ?? now(),
                ]);
                $this->leadSources->assignNewLead($lead);
            }

            return $isNew;
        });
    }

    private function field(array $fields, string $type): ?string
    {
        foreach (self::FIELD_MAP[$type] as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        return $email ? Str::lower(trim($email)) : null;
    }

    private function limitedField(array $fields, string $type, int $maxLength): ?string
    {
        $value = $this->field($fields, $type);

        return $value === null ? null : Str::limit($value, $maxLength, '');
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC');
    }
}
