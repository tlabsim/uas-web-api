<?php

namespace App\Http\Controllers;

use App\Models\EntityStaticPage;
use App\Models\PersonnelAffiliationCache;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_id' => 'required|integer|exists:entity_profiles,entity_id',
            'q' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $entityId = (int) $validated['entity_id'];
        $query = trim((string) $validated['q']);
        $limit = (int) ($validated['limit'] ?? 8);

        if ($query === '') {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'query' => '',
                    'entity_id' => $entityId,
                    'groups' => [
                        ['key' => 'pages', 'label' => 'Pages', 'total' => 0, 'items' => []],
                        ['key' => 'posts', 'label' => 'Posts', 'total' => 0, 'items' => []],
                        ['key' => 'people', 'label' => 'People', 'total' => 0, 'items' => []],
                    ],
                ],
            ]);
        }

        $pages = EntityStaticPage::query()
            ->where('entity_id', $entityId)
            ->where('page_status', 'Published')
            ->where(function ($builder) use ($query) {
                $builder->where('page_title', 'like', '%' . $query . '%')
                    ->orWhere('page_slug', 'like', '%' . $query . '%')
                    ->orWhere('page_content', 'like', '%' . $query . '%');
            })
            ->orderBy('page_title')
            ->limit($limit)
            ->get([
                'id',
                'page_slug',
                'page_title',
                'page_excerpt',
            ]);

        $posts = Post::query()
            ->where('post_status', 'Published')
            ->where(function ($builder) use ($entityId) {
                $builder->where('owner_entity_id', $entityId)
                    ->orWhereHas('taggedEntities', function ($taggedQuery) use ($entityId) {
                        $taggedQuery->where('entity_id', $entityId)
                            ->where('status', 'Approved');
                    });
            })
            ->where(function ($builder) use ($query) {
                $builder->where('post_title', 'like', '%' . $query . '%')
                    ->orWhere('post_excerpt', 'like', '%' . $query . '%')
                    ->orWhere('post_content', 'like', '%' . $query . '%');
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get([
                'id',
                'category',
                'post_title',
                'post_excerpt',
                'published_at',
            ]);

        $people = PersonnelAffiliationCache::query()
            ->where('personnel_affiliations_cache.entity_id', $entityId)
            ->where('personnel_affiliations_cache.is_active', true)
            ->join('personnels_cache', 'personnel_affiliations_cache.personnel_id', '=', 'personnels_cache.personnel_id')
            ->leftJoin('personnel_profiles', 'personnel_affiliations_cache.personnel_id', '=', 'personnel_profiles.personnel_id')
            ->where(function ($builder) use ($query) {
                $builder->where('personnel_profiles.display_name', 'like', '%' . $query . '%')
                    ->orWhere('personnel_profiles.short_bio', 'like', '%' . $query . '%')
                    ->orWhere('personnels_cache.first_name', 'like', '%' . $query . '%')
                    ->orWhere('personnels_cache.last_name', 'like', '%' . $query . '%')
                    ->orWhere('personnels_cache.designation', 'like', '%' . $query . '%')
                    ->orWhereRaw("CONCAT_WS(' ', personnels_cache.title, personnels_cache.first_name, personnels_cache.last_name) LIKE ?", ['%' . $query . '%']);
            })
            ->orderByDesc('personnel_affiliations_cache.is_primary')
            ->orderBy('personnels_cache.seniority_order')
            ->limit($limit)
            ->get([
                'personnel_affiliations_cache.personnel_id',
                'personnel_affiliations_cache.entity_id',
                'personnel_affiliations_cache.entity_name',
                'personnel_affiliations_cache.affiliation_type',
                'personnel_affiliations_cache.is_primary',
                'personnels_cache.title',
                'personnels_cache.first_name',
                'personnels_cache.last_name',
                'personnels_cache.designation',
                'personnels_cache.photo_url',
                'personnel_profiles.display_name',
                'personnel_profiles.short_bio',
            ])
            ->map(function ($item) {
                $fullName = trim(implode(' ', array_filter([
                    $item->title,
                    $item->first_name,
                    $item->last_name,
                ])));

                return [
                    'personnel_id' => $item->personnel_id,
                    'display_name' => $item->display_name ?: $fullName,
                    'full_name_with_title' => $fullName,
                    'designation' => $item->designation,
                    'short_bio' => $item->short_bio,
                    'photo_url' => $item->photo_url,
                    'current_affiliation' => [
                        'entity_id' => (int) $item->entity_id,
                        'entity_name' => $item->entity_name,
                        'affiliation_type' => $item->affiliation_type,
                        'is_primary' => (bool) $item->is_primary,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'query' => $query,
                'entity_id' => $entityId,
                'groups' => [
                    [
                        'key' => 'pages',
                        'label' => 'Pages',
                        'total' => $pages->count(),
                        'items' => $pages->values()->all(),
                    ],
                    [
                        'key' => 'posts',
                        'label' => 'Posts',
                        'total' => $posts->count(),
                        'items' => $posts->values()->all(),
                    ],
                    [
                        'key' => 'people',
                        'label' => 'People',
                        'total' => $people->count(),
                        'items' => $people->all(),
                    ],
                ],
            ],
        ]);
    }
}
