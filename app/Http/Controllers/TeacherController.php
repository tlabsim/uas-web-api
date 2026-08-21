<?php

namespace App\Http\Controllers;

use App\Models\PersonnelAffiliationCache;
use App\Models\PersonnelProfile;
use App\Models\Publication;
use App\Models\Research;
use App\Models\SeminarWorkshopTraining;
use App\Services\ImsTeacherCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TeacherController extends Controller
{
    public function __construct(
        protected ImsTeacherCacheService $teacherCacheService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_id' => 'required|integer|exists:entity_profiles,entity_id',
            'search' => 'nullable|string',
            'primary_only' => 'nullable|boolean',
            'affiliation_type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $entityId = (int) $validated['entity_id'];
        $syncStatus = $this->teacherCacheService->syncEntityTeachersIfStale($entityId);

        $query = PersonnelAffiliationCache::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->join('personnels_cache', 'personnel_affiliations_cache.personnel_id', '=', 'personnels_cache.personnel_id')
            ->select([
                'personnel_affiliations_cache.source_affiliation_id',
                'personnel_affiliations_cache.personnel_id',
                'personnel_affiliations_cache.entity_id',
                'personnel_affiliations_cache.entity_name',
                'personnel_affiliations_cache.is_primary',
                'personnel_affiliations_cache.is_active',
                'personnel_affiliations_cache.affiliation_type',
                'personnels_cache.personnel_type',
                'personnels_cache.title',
                'personnels_cache.first_name',
                'personnels_cache.last_name',
                'personnels_cache.designation',
                'personnels_cache.photo_url',
                'personnels_cache.institutional_mail',
                'personnels_cache.primary_phone',
                'personnels_cache.seniority_order',
                'personnels_cache.primary_affiliation_name',
                'personnels_cache.primary_affiliation_entity_id',
                'personnels_cache.primary_affiliation_type',
            ]);

        if ($request->boolean('primary_only', false)) {
            $query->where('personnel_affiliations_cache.is_primary', true);
        }

        if ($request->filled('affiliation_type')) {
            $types = collect(explode(',', (string) $request->input('affiliation_type')))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();

            if (!empty($types)) {
                $query->whereIn('personnel_affiliations_cache.affiliation_type', $types);
            }
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery
                    ->where('personnels_cache.first_name', 'like', $term)
                    ->orWhere('personnels_cache.last_name', 'like', $term)
                    ->orWhereRaw("CONCAT_WS(' ', personnels_cache.title, personnels_cache.first_name, personnels_cache.last_name) LIKE ?", [$term])
                    ->orWhere('personnels_cache.designation', 'like', $term)
                    ->orWhere('personnels_cache.institutional_mail', 'like', $term);
            });
        }

        $teachers = $query
            ->orderByDesc('personnel_affiliations_cache.is_primary')
            ->orderBy('personnels_cache.seniority_order')
            ->orderBy('personnels_cache.first_name')
            ->paginate((int) ($validated['per_page'] ?? 24))
            ->through(function ($row) {
                $fullName = trim(implode(' ', array_filter([
                    $row->title,
                    $row->first_name,
                    $row->last_name,
                ])));

                return $this->mapTeacherDirectoryRow($row, $fullName);
            });

        return response()->json([
            'status' => 'success',
            'data' => $teachers->items(),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
                'last_page' => $teachers->lastPage(),
                'cache' => [
                    'refreshed' => (bool) ($syncStatus['refreshed'] ?? false),
                    'served_from_cache' => (bool) ($syncStatus['served_from_cache'] ?? true),
                    'stale_fallback' => (bool) ($syncStatus['stale_fallback'] ?? false),
                    'last_synced_at' => $syncStatus['last_synced_at'] ?? null,
                ],
            ],
            'links' => [
                'first' => $teachers->url(1),
                'last' => $teachers->url($teachers->lastPage()),
                'prev' => $teachers->previousPageUrl(),
                'next' => $teachers->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'personnel_id' => 'required|string',
            'entity_id' => 'nullable|integer|exists:entity_profiles,entity_id',
        ]);

        $personnelId = (string) $validated['personnel_id'];
        $entityId = isset($validated['entity_id']) ? (int) $validated['entity_id'] : null;

        $profile = PersonnelProfile::query()
            ->with([
                'cache',
                'affiliationCache',
                'educations',
                'jobExperiences',
                'achievements',
                'professionalProfiles',
                'webSettings',
                'additionalData',
                'researcherProfile.externalProfiles',
            ])
            ->find($personnelId);

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'Teacher profile not found'], 404);
        }

        $affiliations = $profile->affiliationCache
            ->where('is_active', true)
            ->values();

        if ($entityId !== null && !$affiliations->contains(fn ($item) => (int) $item->entity_id === $entityId)) {
            return response()->json(['status' => 'error', 'message' => 'Teacher is not affiliated with the requested entity'], 404);
        }

        $cache = $profile->cache;
        $primaryAffiliation = $affiliations->firstWhere('is_primary', true);

        $publications = Publication::query()
            ->whereHas('authors', function ($query) use ($personnelId) {
                $query->where('internal_author_id', $personnelId)
                    ->where('show_in_profile', true);
            })
            ->with(['authors', 'meta'])
            ->orderByDesc('publication_date')
            ->get();

        $researches = Research::query()
            ->whereHas('people', function ($query) use ($personnelId) {
                $query->where('internal_researcher_id', $personnelId);
            })
            ->with(['people', 'publications'])
            ->orderByDesc('updated_at')
            ->get();

        $trainingSeminars = SeminarWorkshopTraining::query()
            ->where('personnel_id', $personnelId)
            ->orderByDesc('start_date')
            ->get();

        $fullName = trim(implode(' ', array_filter([
            data_get($cache, 'title'),
            data_get($cache, 'first_name'),
            data_get($cache, 'last_name'),
        ])));

        return response()->json([
            'status' => 'success',
            'data' => [
                'personnel_id' => $personnelId,
                'display_name' => $profile->display_name ?: $fullName,
                'full_name_with_title' => $fullName,
                'first_name' => data_get($cache, 'first_name'),
                'last_name' => data_get($cache, 'last_name'),
                'designation' => $profile->display_designation ?: data_get($cache, 'designation'),
                'designation_name' => data_get($cache, 'designation_name'),
                'designation_with_grade' => data_get($cache, 'designation_with_grade'),
                'short_bio' => $profile->short_bio,
                'biography' => $profile->biography,
                'photo_url' => data_get($cache, 'photo_url'),
                'personnel_type' => data_get($cache, 'personnel_type'),
                'primary_affiliation' => $primaryAffiliation ? [
                    'entity_id' => (int) $primaryAffiliation->entity_id,
                    'entity_name' => $primaryAffiliation->entity_name,
                    'entity_display_name' => $primaryAffiliation->entity_display_name ?: $primaryAffiliation->entity_name,
                    'affiliation_type' => $primaryAffiliation->affiliation_type,
                ] : [
                    'entity_id' => data_get($cache, 'primary_affiliation_entity_id'),
                    'entity_name' => data_get($cache, 'primary_affiliation_name'),
                    'entity_display_name' => data_get($cache, 'primary_affiliation_name'),
                    'affiliation_type' => data_get($cache, 'primary_affiliation_type'),
                ],
                'affiliations' => $affiliations->map(fn ($item) => [
                    'entity_id' => (int) $item->entity_id,
                    'entity_name' => $item->entity_name,
                    'entity_display_name' => $item->entity_display_name ?: $item->entity_name,
                    'affiliation_type' => $item->affiliation_type,
                    'is_primary' => (bool) $item->is_primary,
                    'start_date' => optional($item->start_date)->toDateString(),
                    'end_date' => optional($item->end_date)->toDateString(),
                ])->values()->all(),
                'contact' => [
                    'institutional_email' => data_get($cache, 'institutional_mail'),
                    'primary_phone' => data_get($cache, 'primary_phone'),
                ],
                'education' => $profile->educations->map(fn ($item) => [
                    'degree_title' => $item->degree_title,
                    'degree_level' => $item->degree_level,
                    'institution' => $item->institution,
                    'awarding_body' => $item->awarding_body,
                    'remarks' => $item->remarks,
                    'start_month' => $item->start_month,
                    'start_year' => $item->start_year,
                    'end_month' => $item->end_month,
                    'end_year' => $item->end_year,
                    'start_month_year' => optional($item->start_month_year)->toDateString(),
                    'end_month_year' => optional($item->end_month_year)->toDateString(),
                    'passing_year' => $item->passing_year,
                ])->values()->all(),
                'job_experiences' => $profile->jobExperiences->map(fn ($item) => [
                    'job_title' => $item->job_title,
                    'role' => $item->role,
                    'role_description' => $item->role_description,
                    'organization' => $item->organization,
                    'start_date' => optional($item->start_date)->toDateString(),
                    'end_date' => optional($item->end_date)->toDateString(),
                ])->values()->all(),
                'achievements' => $profile->achievements->map(fn ($item) => [
                    'type' => $item->type,
                    'title' => $item->title,
                    'awarding_body' => $item->awarding_body,
                    'award_date' => optional($item->award_date)->toDateString(),
                    'excerpt' => $item->excerpt,
                ])->values()->all(),
                'external_profiles' => $this->mapExternalProfiles($profile),
                'research_interests' => $this->normalizeResearchInterests(data_get($profile, 'researcherProfile.research_interests')),
                'publications' => $publications->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'excerpt' => $item->excerpt,
                    'description' => $item->description,
                    'publication_date' => optional($item->publication_date)->toDateString(),
                    'type' => $item->type,
                    'link_url' => $item->link_url,
                    'keywords' => $item->keywords,
                    'authors' => $item->authors
                        ->sortBy('sl')
                        ->map(fn ($author) => [
                            'name' => $author->author_name,
                            'internal_author_id' => $author->internal_author_id,
                            'is_primary_editor' => (bool) $author->is_primary_editor,
                            'is_editor' => (bool) $author->is_editor,
                        ])->values()->all(),
                ])->values()->all(),
                'researches' => $researches->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'excerpt' => $item->excerpt,
                    'description' => $item->description,
                    'featured_image_uri' => $item->featured_image_uri,
                    'keywords' => $item->keywords,
                    'status' => $item->status,
                    'people' => $item->people
                        ->sortBy('sl')
                        ->map(fn ($person) => [
                            'name' => $person->researcher_name,
                            'internal_researcher_id' => $person->internal_researcher_id,
                            'role' => $person->role,
                        ])->values()->all(),
                ])->values()->all(),
                'training_seminars' => $trainingSeminars->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'attendee_type' => $item->attendee_type,
                    'title' => $item->title,
                    'excerpt' => $item->excerpt,
                    'description' => $item->description,
                    'start_date' => optional($item->start_date)->toDateString(),
                    'end_date' => optional($item->end_date)->toDateString(),
                ])->values()->all(),
                'web_settings' => $this->groupKeyValueCollection($profile->webSettings, 'key_group', 'setting_key'),
                'additional_data' => $this->groupKeyValueCollection($profile->additionalData, 'data_group', 'data_key'),
            ],
        ]);
    }

    protected function mapTeacherDirectoryRow(object $row, string $fullName): array
    {
        return [
            'personnel_id' => $row->personnel_id,
            'personnel_type' => $row->personnel_type,
            'title' => $row->title,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'full_name' => trim(implode(' ', array_filter([$row->first_name, $row->last_name]))),
            'full_name_with_title' => $fullName,
            'designation' => $row->designation,
            'photo_url' => $row->photo_url,
            'institutional_email' => $row->institutional_mail,
            'primary_phone' => $row->primary_phone,
            'primary_affiliation' => $row->primary_affiliation_name,
            'primary_affiliation_entity_id' => $row->primary_affiliation_entity_id,
            'primary_affiliation_type' => $row->primary_affiliation_type,
            'current_affiliation' => [
                'entity_id' => $row->entity_id,
                'entity_name' => $row->entity_name,
                'affiliation_type' => $row->affiliation_type,
                'is_primary' => (bool) $row->is_primary,
            ],
        ];
    }

    protected function mapExternalProfiles(PersonnelProfile $profile): array
    {
        $professionalProfiles = $profile->professionalProfiles
            ->map(fn ($item) => [
                'name' => $item->profile_type,
                'link' => $item->profile_link,
                'source' => 'professional_profile',
            ]);

        $researcherProfiles = collect(data_get($profile, 'researcherProfile.externalProfiles', []))
            ->map(fn ($item) => [
                'name' => $item->profile_type,
                'link' => $item->profile_link,
                'profile_id' => $item->profile_id,
                'source' => 'researcher_profile',
            ]);

        return $professionalProfiles
            ->merge($researcherProfiles)
            ->filter(fn ($item) => filled($item['link'] ?? null))
            ->unique(fn ($item) => Str::lower(($item['name'] ?? '') . '|' . ($item['link'] ?? '')))
            ->values()
            ->all();
    }

    protected function normalizeResearchInterests(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value) && trim($value) !== '') {
            return collect(preg_split('/[,;\n]+/', $value))
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    protected function groupKeyValueCollection(Collection $items, string $groupKeyField, string $itemKeyField): array
    {
        return $items
            ->groupBy(fn ($item) => $item->{$groupKeyField} ?: 'default')
            ->map(function (Collection $groupedItems) use ($itemKeyField) {
                return $groupedItems
                    ->mapWithKeys(fn ($item) => [$item->{$itemKeyField} => $item->value])
                    ->all();
            })
            ->all();
    }
}
