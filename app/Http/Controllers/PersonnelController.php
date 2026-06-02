<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PersonnelProfile;
use App\Models\PersonnelCache;

class PersonnelController extends Controller
{
    /**
     * Show detailed personnel profile.
     * Input: ?personnel_id
     * Example: GET /api/v1/personnel?personnel_id=ab1cde2f...
     */
    public function show(Request $request)
    {
        if (!$request->filled('personnel_id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing personnel_id'], 400);
        }

        $id = $request->personnel_id;

        $profile = PersonnelProfile::with([
            'cache',
            'educations',
            'jobExperiences',
            'achievements',
            'professionalProfiles',
            'webSettings',
            'additionalData',
        ])->find($id);

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'Personnel not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $profile]);
    }
}
