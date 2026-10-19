<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JustdialLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leadid' => ['required', 'string', 'max:191'],
            'leadtype' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:40'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'category' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brancharea' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dncmobile' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'dncphone' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'branchpin' => ['sometimes', 'nullable', 'string', 'max:20'],
            'parentid' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $lead = Lead::query()
                ->where('source', 'justdial')
                ->where('external_id', $validated['leadid'])
                ->first();
            $created = ! $lead;
            $lead ??= new Lead(['source' => 'justdial', 'external_id' => $validated['leadid']]);

            $lead->fill([
                'full_name' => $this->name($validated),
                'company_name' => $this->text($validated['company'] ?? null),
                'email' => isset($validated['email']) ? Str::lower($validated['email']) : null,
                'mobile_number' => $validated['mobile'] ?? $validated['phone'] ?? null,
                'designation' => $this->text($validated['leadtype'] ?? null),
                'requirement' => $this->text($validated['category'] ?? null),
                'location' => $this->location($validated),
                'source_payload' => $request->all(),
                'source_created_at' => $this->sourceCreatedAt($validated),
                'last_synced_at' => now(),
            ]);
            $lead->save();

            if ($created) {
                $lead->update([
                    'lead_code' => 'LD-'.str_pad((string) (1000 + $lead->id), 4, '0', STR_PAD_LEFT),
                ]);
                $lead->histories()->create([
                    'event_type' => 'captured',
                    'title' => 'Lead captured via Justdial',
                    'description' => 'Inbound lead received from the Justdial API.',
                    'metadata' => ['justdial_lead_id' => $validated['leadid']],
                    'occurred_at' => $lead->source_created_at ?? now(),
                ]);
            }

            return [$lead->fresh(), $created];
        });

        /** @var Lead $lead */
        [$lead, $created] = $result;

        return response()->json([
            'status' => true,
            'message' => $created ? 'Justdial lead created successfully.' : 'Justdial lead updated successfully.',
            'data' => [
                'id' => $lead->id,
                'lead_code' => $lead->lead_code,
                'source' => $lead->source,
                'external_id' => $lead->external_id,
                'full_name' => $lead->full_name,
                'company_name' => $lead->company_name,
                'email' => $lead->email,
                'mobile_number' => $lead->mobile_number,
                'requirement' => $lead->requirement,
                'location' => $lead->location,
                'source_created_at' => $lead->source_created_at?->toIso8601String(),
            ],
        ], $created ? 201 : 200);
    }

    private function name(array $data): string
    {
        return $this->text(implode(' ', array_filter([$data['prefix'] ?? null, $data['name']]))) ?? 'Justdial Lead';
    }

    private function location(array $data): ?string
    {
        $parts = array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->text($value),
            Arr::only($data, ['area', 'city', 'brancharea'])
        ))));

        return $parts ? Str::limit(implode(', ', $parts), 255, '') : null;
    }

    private function sourceCreatedAt(array $data): ?Carbon
    {
        if (empty($data['date'])) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $data['date'].' '.($data['time'] ?? '00:00:00'),
            config('services.justdial.timezone', 'Asia/Kolkata')
        )->utc();
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value === '' ? null : $value;
    }
}
