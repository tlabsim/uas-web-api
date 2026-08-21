<?php

namespace App\Http\Controllers;

use App\Models\EntityProgramProfile;
use Illuminate\Http\Request;

class ProgramProfileController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'entity_id' => 'required|integer',
        ]);

        $profiles = EntityProgramProfile::with('heroMediaItem')
            ->where('entity_id', $validated['entity_id'])
            ->where('status', 'Published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (EntityProgramProfile $profile) {
                if ($profile->is_visible) {
                    return $profile;
                }

                return [
                    'id' => $profile->id,
                    'entity_id' => $profile->entity_id,
                    'ims_program_id' => $profile->ims_program_id,
                    'status' => $profile->status,
                    'is_visible' => false,
                    'sort_order' => $profile->sort_order,
                ];
            });

        return response()->json(['status' => 'success', 'data' => $profiles]);
    }

    public function show(Request $request)
    {
        $validated = $request->validate([
            'entity_id' => 'required|integer',
            'program_id' => 'nullable|required_without:slug|integer',
            'slug' => 'nullable|required_without:program_id|string|max:180',
        ]);

        $profile = EntityProgramProfile::with('heroMediaItem')
            ->where('entity_id', $validated['entity_id'])
            ->where('status', 'Published')
            ->where('is_visible', true)
            ->when(
                isset($validated['program_id']),
                fn ($query) => $query->where('ims_program_id', $validated['program_id']),
                fn ($query) => $query->where('slug', $validated['slug'])
            )
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $profile]);
    }
}
