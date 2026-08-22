<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PersonnelProfile;
use App\Models\Research;

class ResearchController extends Controller
{
    /**
     * Show a single research project.
     * Input: ?id
     * Example: GET /api/v1/research?id=42
     */
    public function show(Request $request)
    {
        if (!$request->filled('id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing research ID'], 400);
        }

        $research = Research::with(['people', 'publications.publication.authors', 'publications.publication.meta'])->find($request->id);

        if (!$research) {
            return response()->json(['status' => 'error', 'message' => 'Research not found'], 404);
        }

        $people = $research->people
            ->sortBy('sl')
            ->map(function ($person) {
                $personnelId = $person->internal_researcher_id
                    ? PersonnelProfile::where('researcher_id', $person->internal_researcher_id)->value('personnel_id')
                    : null;

                return [
                    'name' => $person->researcher_name,
                    'internal_researcher_id' => $person->internal_researcher_id,
                    'external_profile_link' => $person->external_researcher_profile_link,
                    'personnel_id' => $personnelId,
                ];
            })
            ->values()
            ->all();

        $publications = $research->publications
            ->map(function ($rp) {
                $pub = $rp->publication;
                if (!$pub) {
                    return [
                        'id' => $rp->publication_id,
                        'title' => $rp->publication_title ?: '',
                        'link' => $rp->publication_link ?: '',
                        'publication_date' => null,
                        'type' => null,
                        'authors' => [],
                        'meta' => [],
                    ];
                }

                return [
                    'id' => $pub->id,
                    'title' => $pub->title,
                    'link' => $pub->link_url ?: ($rp->publication_link ?: ''),
                    'publication_date' => optional($pub->publication_date)->toDateString(),
                    'type' => $pub->type,
                    'authors' => $pub->authors
                        ->sortBy('sl')
                        ->map(fn ($a) => [
                            'name' => $a->author_name,
                            'internal_author_id' => $a->internal_author_id,
                            'external_profile_link' => $a->external_author_profile_link,
                        ])
                        ->values()
                        ->all(),
                    'meta' => $pub->meta
                        ->filter(fn ($m) => $m->meta_value !== null && $m->meta_value !== '')
                        ->mapWithKeys(fn ($m) => [$m->meta_key => $m->meta_value])
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $research->id,
                'title' => $research->title,
                'excerpt' => $research->excerpt,
                'description' => $research->description,
                'featured_image_uri' => $research->featured_image_uri,
                'keywords' => $research->keywords,
                'status' => $research->status,
                'people' => $people,
                'publications' => $publications,
            ],
        ]);
    }
}
