<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonnelCache;
use App\Models\PersonnelProfile;
use App\Models\PersonnelProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PersonnelSelfEditController extends Controller
{
    /**
     * Update the short bio for the currently logged-in personnel.
     */
    public function updateShortBio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'short_bio' => 'required|string|max:300',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $profile = PersonnelProfile::firstOrCreate(
            ['personnel_id' => $personnelId],
            []
        );

        $profile->update(['short_bio' => trim($validated['short_bio'])]);

        return response()->json([
            'status' => 'success',
            'message' => 'Short bio updated.',
            'data' => ['short_bio' => $profile->short_bio],
        ]);
    }

    /**
     * Replace all external profile links for the logged-in personnel.
     */
    public function updateExternalProfiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profiles' => 'required|array',
            'profiles.*.name' => 'required|string|max:100',
            'profiles.*.link' => 'required|string|url|max:1024',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $profile = PersonnelProfile::firstOrCreate(['personnel_id' => $personnelId]);

        // Delete all existing professional profiles for this personnel
        $profile->professionalProfiles()->delete();

        // Re-insert in the provided order
        foreach ($validated['profiles'] as $item) {
            PersonnelProfessionalProfile::create([
                'personnel_id' => $personnelId,
                'profile_type' => $item['name'],
                'profile_link' => $item['link'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'External profiles updated.',
        ]);
    }

    /**
     * Upload / replace the photo for the logged-in personnel.
     * Proxies the file to the IMS self-service photo endpoint.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $accessToken = $request->cookie('ims_access_token');
        if (!$accessToken) {
            return response()->json(['status' => 'error', 'message' => 'Not authenticated with IMS.'], 401);
        }

        $imsBase = rtrim(env('IMS_API_BASE_URL', 'http://ims.nstu.local/api'), '/');

        $response = Http::withToken($accessToken)
            ->attach('photo', file_get_contents($request->file('photo')), $request->file('photo')->getClientOriginalName())
            ->post("{$imsBase}/me/personnel/photo");

        if (!$response->successful()) {
            $body = $response->json();
            return response()->json([
                'status' => 'error',
                'message' => $body['message'] ?? 'Failed to upload photo.',
            ], $response->status());
        }

        // Update local cache so guests see the new photo immediately
        $result = $response->json();
        PersonnelCache::where('personnel_id', $personnelId)->update(['photo_url' => $result['photo_url'] ?? null]);

        return response()->json($result);
    }

    /**
     * Delete the photo for the logged-in personnel.
     * Proxies to the IMS self-service photo endpoint.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $accessToken = $request->cookie('ims_access_token');
        if (!$accessToken) {
            return response()->json(['status' => 'error', 'message' => 'Not authenticated with IMS.'], 401);
        }

        $imsBase = rtrim(env('IMS_API_BASE_URL', 'http://ims.nstu.local/api'), '/');

        $response = Http::withToken($accessToken)
            ->delete("{$imsBase}/me/personnel/photo");

        if (!$response->successful()) {
            $body = $response->json();
            return response()->json([
                'status' => 'error',
                'message' => $body['message'] ?? 'Failed to delete photo.',
            ], $response->status());
        }

        // Update local cache so guests see the removal immediately
        $result = $response->json();
        PersonnelCache::where('personnel_id', $personnelId)->update(['photo_url' => $result['photo_url'] ?? null]);

        return response()->json($result);
    }

    private function readImsUser(Request $request): array
    {
        $cookie = $request->cookie('ims_user');
        if (!$cookie) {
            return [];
        }

        $decoded = json_decode($cookie, true);
        return is_array($decoded) ? $decoded : [];
    }
}
