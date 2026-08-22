<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fetches a personnel's additional contacts and office addresses from IMS
 * (the source of truth) using the unified "WebAPIClient" API key, server-side.
 *
 * Results are short-cached (default 5 minutes) and failures degrade to empty
 * lists so a faculty profile page never breaks if IMS is unreachable.
 */
class ImsContactService
{
    public function additionalContacts(string $personnelId): array
    {
        return $this->cached("ims:contacts:{$personnelId}", function () use ($personnelId) {
            $payload = $this->request("personnels/{$personnelId}/additional-contacts");
            $items = data_get($payload, 'data', []);

            return collect($items)
                ->map(fn ($c) => [
                    'contact_type' => (string) ($c['contact_type'] ?? ''),
                    'contact_value' => (string) ($c['contact_value'] ?? ''),
                    'visibility' => (string) ($c['visibility'] ?? ''),
                    'label' => (string) ($c['label'] ?? ''),
                ])
                ->values()
                ->all();
        });
    }

    public function offices(string $personnelId): array
    {
        return $this->cached("ims:offices:{$personnelId}", function () use ($personnelId) {
            $payload = $this->request("personnels/{$personnelId}/office-addresses");
            $items = data_get($payload, 'data', []);

            $offices = collect($items)
                ->map(fn ($o) => $this->normalizeOffice($o))
                ->values()
                ->all();

            // Primary office first, then the rest in source order.
            usort($offices, fn ($a, $b) => ($b['is_primary'] <=> $a['is_primary']));

            return $offices;
        });
    }

    /**
     * Fetch both additional contacts and offices in one call (two IMS requests).
     */
    public function contactBlock(string $personnelId): array
    {
        return [
            'additional_contacts' => $this->additionalContacts($personnelId),
            'offices' => $this->offices($personnelId),
        ];
    }

    protected function normalizeOffice(array $o): array
    {
        $internal = $o['internal_address'] ?? null;
        $building = $internal['building'] ?? null;
        $campus = $building['campus'] ?? null;

        $officeHours = $o['office_hours'] ?? [];
        if (!is_array($officeHours)) {
            $officeHours = [];
        }

        return [
            'label' => (string) ($o['label'] ?? ''),
            'full_address' => (string) ($o['full_address'] ?? ''),
            'room_floor' => (string) ($o['room_floor'] ?? ($internal['room_floor'] ?? '')),
            'notes' => (string) ($o['notes'] ?? ''),
            'is_primary' => (bool) ($o['is_primary'] ?? false),
            'formatted_office_hours' => (string) ($o['formatted_office_hours'] ?? ''),
            'office_hours' => $this->normalizeOfficeHours($officeHours),
            'building' => (string) ($building['name'] ?? ''),
            'campus' => (string) ($campus['name'] ?? ''),
        ];
    }

    /**
     * Normalize office_hours into an ordered list of {day, available, from, to},
     * preserving the day key (sunday..saturday) that IMS stores.
     */
    protected function normalizeOfficeHours(array $officeHours): array
    {
        $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        return collect($weekdays)
            ->map(function ($day) use ($officeHours) {
                $schedule = $officeHours[$day] ?? null;
                if (!is_array($schedule)) {
                    return null;
                }
                return [
                    'day' => $day,
                    'available' => (bool) ($schedule['available'] ?? false),
                    'from' => (string) ($schedule['from'] ?? ''),
                    'to' => (string) ($schedule['to'] ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function request(string $path): array
    {
        $baseUrl = rtrim((string) config('ims.api_base_url'), '/');
        $apiKey = (string) config('ims.api_key');

        if ($baseUrl === '') {
            throw new RuntimeException('IMS API base URL is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(10)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->get($path);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Failed to reach IMS while fetching personnel contact data.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch {$path} from IMS: {$response->status()}");
        }

        $payload = $response->json();
        return is_array($payload) ? $payload : [];
    }

    protected function cached(string $key, callable $resolver): array
    {
        $ttl = (int) config('ims.contact_cache_threshold', 5);

        try {
            return Cache::remember($key, now()->addMinutes($ttl), function () use ($resolver) {
                return $resolver();
            });
        } catch (Throwable $exception) {
            Log::warning('IMS contact/office fetch failed; returning empty list.', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
