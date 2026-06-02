<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Models\EntityProfile;
use App\Models\EntityCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $entityId = $request->input('entity_id');

        if (!$entityId) {
            return response()->json([
                'success' => false,
                'message' => 'Entity ID is required.',
            ], 400);
        }

        try {
            // Get profile (create if doesn't exist)
            $profile = EntityProfile::firstOrCreate(
                ['entity_id' => $entityId],
                [
                    'establishment_date' => null,
                    'slug' => null,
                    'head_personnel_id' => null,
                    'head_role_assignment_id' => null,
                    'head_role_name' => null,
                    'head_info_name' => null,
                    'head_info_designation' => null,
                    'head_info_photo_url' => null,
                    'head_message' => null,
                ]
            );

            // Get cached entity data
            $entityCache = EntityCache::where('entity_id', $entityId)->first();

            // Prepare entity data with fallbacks
            $entityData = [
                'entity_id' => $profile->entity_id,
                'establishment_date' => $profile->establishment_date,
                'slug' => $profile->slug,
                'head_personnel_id' => $profile->head_personnel_id,
                'head_role_assignment_id' => $profile->head_role_assignment_id,
                'head_role_name' => $profile->head_role_name,
                'head_info_name' => $profile->head_info_name,
                'head_info_designation' => $profile->head_info_designation,
                'head_info_photo_url' => $profile->head_info_photo_url,
                'head_message' => $profile->head_message,
            ];

            if ($entityCache) {
                $entityData = array_merge($entityData, [
                    'entity_name' => $entityCache->full_name ?? $entityCache->name ?? 'Unknown Entity',
                    'entity_short_name' => $entityCache->short_name ?? null,
                    'entity_title' => $entityCache->title ?? null,
                    'entity_type' => $entityCache->entity_type ?? null,
                    'entity_category' => $entityCache->entity_category ?? null,
                    'parent_entity_id' => $entityCache->parent_entity_id ?? null,
                    'parent_entity_name' => $entityCache->parent_entity_name ?? null,
                    'logo_url' => $entityCache->logo_url ?? null,
                    'name' => $entityCache->name ?? null,
                    'full_name' => $entityCache->full_name ?? null,
                ]);
            } else {
                // Fallback when no cache exists
                $entityData = array_merge($entityData, [
                    'entity_name' => 'Unknown Entity',
                    'entity_short_name' => null,
                    'entity_title' => null,
                    'entity_type' => null,
                    'entity_category' => null,
                    'parent_entity_id' => null,
                    'parent_entity_name' => null,
                    'logo_url' => null,
                    'name' => null,
                    'full_name' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $entityData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching entity profile', [
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch entity profile.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity_id' => 'required|integer',
            'establishment_date' => 'nullable|date',
            'slug' => 'required|string|max:50|alpha_dash',
            'head_personnel_id' => 'nullable|string',
            'head_role_assignment_id' => 'nullable|integer',
            'head_role_name' => 'nullable|string|max:240',
            'head_info_name' => 'nullable|string|max:240',
            'head_info_designation' => 'nullable|string|max:240',
            'head_info_photo_url' => 'nullable|string',
            'head_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $entityId = $validated['entity_id'];

        try {
            // Check slug uniqueness (excluding current entity)
            $existingSlug = EntityProfile::where('slug', $validated['slug'])
                ->where('entity_id', '!=', $entityId)
                ->exists();

            if ($existingSlug) {
                return response()->json([
                    'success' => false,
                    'message' => 'This slug is already in use by another entity.',
                ], 422);
            }

            // Update or create profile
            $profile = EntityProfile::updateOrCreate(
                ['entity_id' => $entityId],
                [
                    'establishment_date' => $validated['establishment_date'],
                    'slug' => $validated['slug'],
                    'head_personnel_id' => $validated['head_personnel_id'] ?? null,
                    'head_role_assignment_id' => $validated['head_role_assignment_id'] ?? null,
                    'head_role_name' => $validated['head_role_name'] ?? null,
                    'head_info_name' => $validated['head_info_name'] ?? null,
                    'head_info_designation' => $validated['head_info_designation'] ?? null,
                    'head_info_photo_url' => $validated['head_info_photo_url'] ?? null,
                    'head_message' => $validated['head_message'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Entity profile updated successfully.',
                'data' => $profile,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating entity profile', [
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update entity profile.',
            ], 500);
        }
    }
}
