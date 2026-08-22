<?php

namespace App\Services;

use App\Models\PersonnelCache;
use App\Models\PersonnelProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImsPersonnelCacheService
{
    public function resolvePhotoUrl(string $personnelId, ?int $thresholdMinutes = null): ?string
    {
        $thresholdMinutes ??= (int) config('ims.personnel_cache_update_threshold', 15);
        $cached = PersonnelCache::query()->find($personnelId);

        if ($cached && Carbon::parse($cached->updated_at)->addMinutes($thresholdMinutes)->isFuture()) {
            return $cached->photo_url;
        }

        try {
            $personnel = $this->fetchPersonnel($personnelId);
            if ($personnel === null) {
                return $cached?->photo_url;
            }

            DB::transaction(function () use ($personnelId, $personnel) {
                PersonnelProfile::query()->firstOrCreate(['personnel_id' => $personnelId]);

                PersonnelCache::query()->updateOrCreate(
                    ['personnel_id' => $personnelId],
                    [
                        'personnel_type' => data_get($personnel, 'personnel_type', 'Other'),
                        'title' => data_get($personnel, 'title'),
                        'title_bn' => data_get($personnel, 'title_bn'),
                        'first_name' => (string) data_get($personnel, 'first_name', ''),
                        'first_name_bn' => (string) data_get($personnel, 'first_name_bn', ''),
                        'last_name' => data_get($personnel, 'last_name'),
                        'last_name_bn' => data_get($personnel, 'last_name_bn'),
                        'sex' => data_get($personnel, 'sex', 'Other'),
                        'designation' => (string) (
                            data_get($personnel, 'designation.designation_with_grade')
                            ?? data_get($personnel, 'designation_name')
                            ?? 'Unknown'
                        ),
                        'designation_name' => data_get($personnel, 'designation_name'),
                        'designation_with_grade' => data_get($personnel, 'designation.designation_with_grade'),
                        'pin' => data_get($personnel, 'pin'),
                        'seniority_order' => data_get($personnel, 'seniority_order'),
                        'institutional_mail' => data_get($personnel, 'institutional_email'),
                        'primary_phone' => data_get($personnel, 'primary_phone'),
                        'photo_url' => data_get($personnel, 'photo_url'),
                        'employment_type' => data_get($personnel, 'employment_type'),
                        'date_of_joining' => data_get($personnel, 'date_of_joining'),
                        'status' => data_get($personnel, 'status'),
                    ]
                );
            });

            return data_get($personnel, 'photo_url');
        } catch (Throwable $exception) {
            Log::warning('IMS personnel cache refresh failed; using cached photo URL.', [
                'personnel_id' => $personnelId,
                'error' => $exception->getMessage(),
            ]);

            return $cached?->photo_url;
        }
    }

    protected function fetchPersonnel(string $personnelId): ?array
    {
        $response = Http::baseUrl(rtrim((string) config('ims.api_base_url'), '/'))
            ->acceptJson()
            ->timeout(10)
            ->withHeaders(['X-API-KEY' => (string) config('ims.api_key')])
            ->get('personnels', [
                'id' => $personnelId,
                'include' => 'designation',
                'per_page' => 1,
            ])
            ->throw();

        return data_get($response->json(), 'data.0');
    }
}
