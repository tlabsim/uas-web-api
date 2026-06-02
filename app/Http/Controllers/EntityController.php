<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EntityProfile;
use App\Models\EntityCache;
use App\Models\EntityWebSetting;
use App\Models\EntityPageCategory;

class EntityController extends Controller
{
    /**
     * List all entities (optionally filtered by type or category).
     * Example: GET /api/v1/entities?type=Department
     */
    public function index(Request $request)
    {
        //Update cache if needed
        //Cache needs updating if the last updated_at timestamp in EntityCache is older than a certain threshold in minutes.
        $cacheThreshold = config('ims.cache_update_threshold', 10); // Default to 60 minutes if not set
        $lastCacheUpdate = EntityCache::max('updated_at');
        $lastCacheUpdate = $lastCacheUpdate ? \Carbon\Carbon::parse($lastCacheUpdate) : null;
        if (!$lastCacheUpdate || $lastCacheUpdate->diffInMinutes(now()) > $cacheThreshold) {
            $this->updateCache();
        }

        // Also force update cache if no corresponding Cache not found
        if (EntityCache::count() == EntityProfile::count()) {
            $this->updateCache();
        }        

        $entities = EntityProfile::with('cachedData')
            ->whereHas('cachedData', function ($query) use ($request) {
                $query->whereNotNull('entity_id');

                if ($request->filled('type')) {
                    $query->where('entity_type', $request->type);
                }

                if ($request->filled('category')) {
                    $query->where('entity_category', $request->category);
                }

                $query->orderBy('entity_order');
            })->get();

        return response()->json([
            'status' => 'success',
            'data' => $entities
        ]);
    }

    public function updateCache($entityId = null)
    {
        // Update EntityCache table by pulling data from IMS
        if($entityId) {
            $profile = EntityProfile::find($entityId);
            if (!$profile) {
                return response()->json(['status' => 'error', 'message' => 'Entity not found'], 404);
            }
            else{
                // Query IMS for entity data
                $entity_query_url = config('ims.api_base_url') . '/entities/' . $profile->entity_id;
                $response = file_get_contents($entity_query_url);
                if ($response === false) {
                    return response()->json(['status' => 'error', 'message' => 'Failed to fetch entity data from IMS'], 500);
                }
                $entityDataResponse = json_decode($response);
                if (!$entityDataResponse) {
                    return response()->json(['status' => 'error', 'message' => 'Invalid response from IMS'], 500);
                }

                $entityData = EntityCache::where('entity_id', $entityId)->first();

                if (!$entityData) {
                    // Create a new cache entry if it doesn't exist
                    $entityData = new EntityCache();
                    $entityData->entity_id = $profile->entity_id;
                }
                else
                {
                    // Skip if $entityDataResponse->updated_at is not newer than the existing cache
                    if (isset($entityDataResponse->updated_at) && $entityData->updated_at >= $entityDataResponse->updated_at) {
                        return response()->json(['status' => 'success', 'message' => 'Cache is already up-to-date'], 200);
                    }
                }
                
                // Update the cache with the new data
                $entityData->entity_type = $entityDataResponse->type->name ?? null;
                $entityData->entity_category = $entityDataResponse->type->entity_category ?? null;
                if($entityDataResponse->parent) {
                    $entityData->parent_entity_id = $entityDataResponse->parent->id ?? null;
                    $entityData->parent_entity_name = $entityDataResponse->parent->full_name ?? null;
                } else {
                    $entityData->parent_entity_id = null;
                    $entityData->parent_entity_name = null;
                }
                $entityData->title = $entityDataResponse->title ?? null;
                $entityData->title_bn = $entityDataResponse->title_bn ?? null;
                $entityData->name = $entityDataResponse->name ?? null;
                $entityData->name_bn = $entityDataResponse->name_bn ?? null;
                $entityData->short_name = $entityDataResponse->short_name ?? null;
                $entityData->short_name_bn = $entityDataResponse->short_name_bn ?? null;
                $entityData->description = $entityDataResponse->description ?? null;
                $entityData->logo_url = $entityDataResponse->logo_src ?? null;
                $entityData->entity_order = $entityDataResponse->entity_order ?? 0;
                // Set timestamps
                $entityData->updated_at = now();

                // Save the updated cache
                $entityData->save();

            }
        } else {
            // If no specific entity_id is provided, update all entities
            $profile = EntityProfile::all();
            if ($profile->isEmpty()) {
                return response()->json(['status' => 'error', 'message' => 'No entities found'], 404);
            }

            // To avoid making multiple API calls, we can fetch all entities info at once by calling /entities endpoint
            $entity_query_url = config('ims.api_base_url') . '/entities';
            $response = file_get_contents($entity_query_url);
            if ($response === false) {
                return response()->json(['status' => 'error', 'message' => 'Failed to fetch entities data from IMS'], 500);
            }

            $entitiesDataResponse = json_decode($response);
            if (!$entitiesDataResponse || !is_array($entitiesDataResponse)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid response from IMS'], 500);
            }
            
            //For each entity in profile, find corresponsing response item, and update or create the cache entry
            $entitiesDataResponse = collect($entitiesDataResponse);
            foreach ($profile as $entityProfile) {
                // Find the corresponding entity data in the response
                $entityDataResponse = $entitiesDataResponse->firstWhere('id', $entityProfile->entity_id);
                if (!$entityDataResponse) {
                    continue; // Skip if no data found for this entity
                }

                $entityData = EntityCache::where('entity_id', $entityProfile->entity_id)->first();

                if (!$entityData) {
                    // Create a new cache entry if it doesn't exist
                    $entityData = new EntityCache();
                    $entityData->entity_id = $entityProfile->entity_id;
                }
                else
                {
                    // Skip if $entityDataResponse->updated_at is not newer than the existing cache
                    if (isset($entityDataResponse->updated_at) && $entityData->updated_at >= $entityDataResponse->updated_at) {
                        continue; // Skip this entity, cache is already up-to-date
                    }
                }

                // Update the cache with the new data
                $entityData->entity_type = $entityDataResponse->type->name ?? null;
                $entityData->entity_category = $entityDataResponse->type->entity_category ?? null;
                if($entityDataResponse->parent) {
                    $entityData->parent_entity_id = $entityDataResponse->parent->id ?? null;
                    $entityData->parent_entity_name = $entityDataResponse->parent->full_name ?? null;
                } else {
                    $entityData->parent_entity_id = null;
                    $entityData->parent_entity_name = null;
                }
                $entityData->title = $entityDataResponse->title ?? null;
                $entityData->title_bn = $entityDataResponse->title_bn ?? null;
                $entityData->name = $entityDataResponse->name ?? null;
                $entityData->name_bn = $entityDataResponse->name_bn ?? null;
                $entityData->short_name = $entityDataResponse->short_name ?? null;
                $entityData->short_name_bn = $entityDataResponse->short_name_bn ?? null;
                $entityData->description = $entityDataResponse->description ?? null;
                $entityData->logo_url = $entityDataResponse->logo_src ?? null;
                $entityData->entity_order = $entityDataResponse->entity_order ?? 0;
                // Set timestamps
                $entityData->updated_at = now();

                // Save the updated cache
                $entityData->save();
            }
        }
        return response()->json(['status' => 'success', 'message' => 'Cache updated successfully']);
    }

    /**
     * Show a specific entity by entity_id or slug.
     * Example: GET /api/v1/entity?entity_id=5 or ?slug=cste
     */
    public function show(Request $request)
    {
        if ($request->filled('entity_id')) {
            // See if cached data exists and not older than ims.cache_update_threshold in minutes


            $cachedData = EntityCache::where('entity_id', $request->entity_id)->first();
            $profile = EntityProfile::with('cachedData')->find($request->entity_id);
        } elseif ($request->filled('slug')) {
            $profile = EntityProfile::with('cachedData')->where('slug', $request->slug)->first();
        } else {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id or slug'], 400);
        }

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'Entity not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $profile]);
    }
    

    /**
     * Get menu categories and subcategories for an entity.
     * Example: GET /api/v1/entity/menus?entity_id=5
     */
    public function menus(Request $request)
    {
        $entityId = $request->entity_id;

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id'], 400);
        }

        $categories = EntityPageCategory::with('subcategories')
            ->where('entity_id', $entityId)
            ->orderBy('menu_order')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    /**
     * Return public web settings (key-value pairs) for the entity.
     * Example: GET /api/v1/entity/settings?entity_id=5
     */
    public function settings(Request $request)
    {
        $entityId = $request->entity_id;

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id'], 400);
        }

        $settings = EntityWebSetting::where('entity_id', $entityId)->get();

        $grouped = $settings->groupBy('key_group')->map(function ($group) {
            return $group->pluck('value', 'setting_key');
        });

        return response()->json(['status' => 'success', 'data' => $grouped]);
    }
}
