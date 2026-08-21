<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\EntityProgramProfile;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProgramProfileController extends Controller
{
    public function index(Request $request)
    {
        $entityId = $this->entityId($request);

        $profiles = EntityProgramProfile::with('heroMediaItem')
            ->where('entity_id', $entityId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $profiles]);
    }

    public function show(Request $request, int $programId)
    {
        $profile = EntityProgramProfile::with('heroMediaItem')
            ->where('entity_id', $this->entityId($request))
            ->where('ims_program_id', $programId)
            ->first();

        return response()->json(['status' => 'success', 'data' => $profile]);
    }

    public function upsert(Request $request, int $programId)
    {
        $entityId = $this->entityId($request);
        $profile = EntityProgramProfile::where('entity_id', $entityId)
            ->where('ims_program_id', $programId)
            ->first();

        $validated = $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('entity_program_profiles', 'slug')
                    ->where(fn ($query) => $query->where('entity_id', $entityId))
                    ->ignore($profile?->id),
            ],
            'display_title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'summary' => 'nullable|string|max:2000',
            'hero_media_item_id' => 'nullable|integer',
            'overview' => 'nullable|string',
            'learning_outcomes' => 'nullable|string',
            'admission_requirements' => 'nullable|string',
            'curriculum' => 'nullable|string',
            'career_opportunities' => 'nullable|string',
            'fees_and_funding' => 'nullable|string',
            'accreditation' => 'nullable|string|max:500',
            'application_label' => 'nullable|string|max:100',
            'application_url' => 'nullable|url|max:1000',
            'brochure_url' => 'nullable|url|max:1000',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:100',
            'custom_sections' => 'nullable|array|max:12',
            'custom_sections.*.title' => 'required|string|max:255',
            'custom_sections.*.content' => 'nullable|string',
            'status' => 'required|in:Draft,Published',
            'is_visible' => 'required|boolean',
            'is_featured' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:10000',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
        ]);

        if (!empty($validated['hero_media_item_id'])) {
            $ownsMedia = MediaItem::where('id', $validated['hero_media_item_id'])
                ->where('owner_entity_id', $entityId)
                ->where('media_type', 'image')
                ->exists();

            if (!$ownsMedia) {
                return response()->json([
                    'message' => 'The selected hero image does not belong to this entity.',
                    'errors' => ['hero_media_item_id' => ['Choose an image from this entity media library.']],
                ], 422);
            }
        }

        $profile = DB::transaction(function () use ($entityId, $programId, $profile, $validated) {
            $publishedAt = $profile?->published_at;
            if ($validated['status'] === 'Published' && !$publishedAt) {
                $publishedAt = now();
            }

            return EntityProgramProfile::updateOrCreate(
                ['entity_id' => $entityId, 'ims_program_id' => $programId],
                array_merge($validated, ['published_at' => $publishedAt])
            );
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Program website profile saved successfully.',
            'data' => $profile->load('heroMediaItem'),
        ]);
    }

    public function destroy(Request $request, int $programId)
    {
        EntityProgramProfile::where('entity_id', $this->entityId($request))
            ->where('ims_program_id', $programId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Program website customization reset successfully.',
        ]);
    }

    public function reorder(Request $request)
    {
        $entityId = $this->entityId($request);
        $validated = $request->validate([
            'programs' => 'required|array',
            'programs.*.program_id' => 'required|integer',
            'programs.*.sort_order' => 'required|integer|min:0|max:10000',
        ]);

        DB::transaction(function () use ($entityId, $validated) {
            foreach ($validated['programs'] as $item) {
                EntityProgramProfile::where('entity_id', $entityId)
                    ->where('ims_program_id', $item['program_id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['status' => 'success', 'message' => 'Program order updated.']);
    }

    private function entityId(Request $request): int
    {
        $entityId = (int) $request->attributes->get('current_role_scope');

        abort_if($entityId <= 0, 403, 'Entity scope not found.');

        return $entityId;
    }
}
