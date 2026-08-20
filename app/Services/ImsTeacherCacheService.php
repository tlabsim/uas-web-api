<?php

namespace App\Services;

use App\Models\EntityCache;
use App\Models\PersonnelAffiliationCache;
use App\Models\PersonnelCache;
use App\Models\PersonnelProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImsTeacherCacheService
{
    public function syncEntityTeachersIfStale(int $entityId, ?int $thresholdMinutes = null): array
    {
        $thresholdMinutes ??= (int) config('ims.teacher_directory_cache_update_threshold', 15);
        $entityCache = EntityCache::find($entityId);

        if (!$entityCache) {
            throw new RuntimeException("Entity cache not found for entity [{$entityId}].");
        }

        if (!$this->isSyncRequired($entityCache, $thresholdMinutes)) {
            return [
                'refreshed' => false,
                'served_from_cache' => true,
                'stale_fallback' => false,
                'last_synced_at' => $entityCache->teachers_cache_synced_at,
            ];
        }

        try {
            $payloadItems = $this->fetchTeachersForEntity($entityId);
        } catch (Throwable $exception) {
            if ($this->hasCachedTeachers($entityId)) {
                Log::warning('Teacher directory refresh failed; serving stale cache.', [
                    'entity_id' => $entityId,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'refreshed' => false,
                    'served_from_cache' => true,
                    'stale_fallback' => true,
                    'last_synced_at' => $entityCache->teachers_cache_synced_at,
                ];
            }

            throw $exception;
        }

        $sourceAffiliationIds = [];

        DB::transaction(function () use ($entityId, $entityCache, $payloadItems, &$sourceAffiliationIds) {
            foreach ($payloadItems as $teacher) {
                $personnelId = (string) data_get($teacher, 'id');
                if ($personnelId === '') {
                    continue;
                }

                $profile = PersonnelProfile::query()->find($personnelId);
                if (!$profile) {
                    $profile = new PersonnelProfile();
                    $profile->personnel_id = $personnelId;
                    $profile->save();
                }

                $primaryAffiliation = collect(data_get($teacher, 'affiliations', []))
                    ->first(fn ($aff) => (bool) data_get($aff, 'is_primary', false));

                PersonnelCache::query()->updateOrCreate(
                    ['personnel_id' => $personnelId],
                    [
                        'personnel_type' => (string) data_get($teacher, 'personnel_type', 'Teacher'),
                        'title' => data_get($teacher, 'title'),
                        'title_bn' => data_get($teacher, 'title_bn'),
                        'first_name' => (string) data_get($teacher, 'first_name', ''),
                        'first_name_bn' => (string) data_get($teacher, 'first_name_bn', ''),
                        'last_name' => data_get($teacher, 'last_name'),
                        'last_name_bn' => data_get($teacher, 'last_name_bn'),
                        'sex' => (string) data_get($teacher, 'sex', 'Other'),
                        'designation' => $this->resolveDesignation($teacher),
                        'designation_name' => $this->resolveDesignationName($teacher),
                        'designation_with_grade' => $this->resolveDesignationWithGrade($teacher),
                        'pin' => data_get($teacher, 'pin'),
                        'seniority_order' => data_get($teacher, 'seniority_order'),
                        'institutional_mail' => data_get($teacher, 'institutional_email'),
                        'primary_phone' => data_get($teacher, 'primary_phone'),
                        'photo_url' => data_get($teacher, 'photo_url'),
                        'employment_type' => data_get($teacher, 'employment_type'),
                        'date_of_joining' => data_get($teacher, 'date_of_joining'),
                        'status' => data_get($teacher, 'status'),
                        'primary_affiliation_entity_id' => data_get($primaryAffiliation, 'academic_unit_id'),
                        'primary_affiliation_name' => data_get($primaryAffiliation, 'academic_unit_name'),
                        'primary_affiliation_type' => data_get($primaryAffiliation, 'affiliation_type'),
                        'affiliations_cached_at' => now(),
                    ]
                );

                foreach ((array) data_get($teacher, 'affiliations', []) as $affiliation) {
                    $sourceAffiliationId = data_get($affiliation, 'id');
                    if (!$sourceAffiliationId) {
                        continue;
                    }

                    $sourceAffiliationIds[] = (int) $sourceAffiliationId;

                    PersonnelAffiliationCache::query()->updateOrCreate(
                        ['source_affiliation_id' => (int) $sourceAffiliationId],
                        [
                            'personnel_id' => $personnelId,
                            'entity_id' => (int) data_get($affiliation, 'academic_unit_id'),
                            'entity_name' => data_get($affiliation, 'academic_unit_name'),
                            'entity_display_name' => data_get($affiliation, 'academic_unit.display_name')
                                ?: data_get($affiliation, 'academic_unit_name'),
                            'is_primary' => (bool) data_get($affiliation, 'is_primary', false),
                            'is_active' => (bool) data_get($affiliation, 'is_active', true),
                            'affiliation_type' => data_get($affiliation, 'affiliation_type'),
                            'start_date' => data_get($affiliation, 'start_date'),
                            'end_date' => data_get($affiliation, 'end_date'),
                            'synced_at' => now(),
                        ]
                    );
                }
            }

            PersonnelAffiliationCache::query()
                ->where('entity_id', $entityId)
                ->when(!empty($sourceAffiliationIds), function ($query) use ($sourceAffiliationIds) {
                    $query->whereNotIn('source_affiliation_id', $sourceAffiliationIds);
                })
                ->when(empty($sourceAffiliationIds), fn ($query) => $query)
                ->delete();

            $entityCache->forceFill([
                'teachers_cache_synced_at' => now(),
            ])->save();
        });

        return [
            'refreshed' => true,
            'served_from_cache' => false,
            'stale_fallback' => false,
            'last_synced_at' => $entityCache->fresh()->teachers_cache_synced_at,
        ];
    }

    public function hasCachedTeachers(int $entityId): bool
    {
        return PersonnelAffiliationCache::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->exists();
    }

    protected function isSyncRequired(EntityCache $entityCache, int $thresholdMinutes): bool
    {
        $lastSyncedAt = $entityCache->teachers_cache_synced_at
            ? Carbon::parse($entityCache->teachers_cache_synced_at)
            : null;

        if (!$lastSyncedAt) {
            return true;
        }

        return $lastSyncedAt->diffInMinutes(now()) >= $thresholdMinutes;
    }

    protected function fetchTeachersForEntity(int $entityId): array
    {
        $items = [];
        $page = 1;

        do {
            $payload = $this->requestTeachers($entityId, $page);
            $batch = (array) data_get($payload, 'data', []);
            $items = array_merge($items, $batch);

            $currentPage = (int) data_get($payload, 'meta.current_page', $page);
            $lastPage = (int) data_get($payload, 'meta.last_page', $currentPage);
            $page++;
        } while ($currentPage < $lastPage);

        return $items;
    }

    protected function requestTeachers(int $entityId, int $page): array
    {
        $baseUrl = rtrim((string) config('ims.api_base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('IMS API base URL is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(15)
                ->get('personnels', [
                    'type' => 'Teacher',
                    'status' => 'Active',
                    'affiliated_with' => $entityId,
                    'include' => 'designation,affiliations',
                    'per_page' => 100,
                    'page' => $page,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                "Failed to reach IMS while syncing teachers for entity [{$entityId}].",
                previous: $exception
            );
        }

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch teachers from IMS. ' . $response->status());
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('Unexpected teacher sync payload from IMS.');
        }

        return $payload;
    }

    protected function resolveDesignation(array $teacher): string
    {
        return (string) (
            data_get($teacher, 'designation.designation_with_grade')
            ?? data_get($teacher, 'designation_name')
            ?? data_get($teacher, 'designation.name')
            ?? 'Teacher'
        );
    }

    protected function resolveDesignationName(array $teacher): ?string
    {
        $name = data_get($teacher, 'designation_name')
            ?? data_get($teacher, 'designation.name');

        return $name ? (string) $name : null;
    }

    protected function resolveDesignationWithGrade(array $teacher): ?string
    {
        $withGrade = data_get($teacher, 'designation.designation_with_grade');

        return $withGrade ? (string) $withGrade : null;
    }
}
