<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonnelCache;
use App\Models\PersonnelEducation;
use App\Models\PersonnelJobExperience;
use App\Models\PersonnelProfile;
use App\Models\PersonnelProfessionalProfile;
use App\Models\Researcher;
use App\Models\SeminarWorkshopTraining;
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
     * Update the long biography for the currently logged-in personnel.
     */
    public function updateBiography(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'biography' => 'nullable|string|max:5000',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $profile = PersonnelProfile::firstOrCreate(['personnel_id' => $personnelId]);

        $biography = trim((string) ($validated['biography'] ?? ''));
        $profile->update(['biography' => $biography]);

        return response()->json([
            'status' => 'success',
            'message' => 'Biography updated.',
            'data' => ['biography' => $biography],
        ]);
    }

    /**
     * Replace the education history for the logged-in personnel.
     */
    public function updateEducation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'educations' => 'present|array',
            'educations.*.degree_title' => 'required|string|max:512',
            'educations.*.degree_level' => 'required|in:Primary,Secondary,Higher Secondary,Undergraduate,Post-graduate,Doctoral,Post-doctoral',
            'educations.*.institution' => 'required|string|max:255',
            'educations.*.awarding_body' => 'nullable|string|max:255',
            'educations.*.remarks' => 'nullable|string|max:2000',
            'educations.*.start_month_year' => 'nullable|date',
            'educations.*.end_month_year' => 'nullable|date',
            'educations.*.passing_year' => 'required|string|size:4',
        ], [
            'educations.*.degree_title.required' => 'Provide a degree title.',
            'educations.*.degree_title.max' => 'Provide a shorter degree title.',
            'educations.*.institution.required' => 'Provide an institution.',
            'educations.*.passing_year.required' => 'Provide a passing year.',
            'educations.*.passing_year.size' => 'Provide a 4-digit year, e.g. 2021.',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $profile = PersonnelProfile::firstOrCreate(['personnel_id' => $personnelId]);

        // Delete all existing education records
        $profile->educations()->delete();

        $created = [];
        foreach ($validated['educations'] as $item) {
            $created[] = PersonnelEducation::create([
                'personnel_id' => $personnelId,
                'degree_title' => trim($item['degree_title']),
                'degree_level' => $item['degree_level'],
                'institution' => trim($item['institution']),
                'awarding_body' => isset($item['awarding_body']) && $item['awarding_body'] !== '' ? trim($item['awarding_body']) : null,
                'remarks' => isset($item['remarks']) && $item['remarks'] !== '' ? trim($item['remarks']) : null,
                'start_month_year' => $item['start_month_year'] ?? null,
                'end_month_year' => $item['end_month_year'] ?? null,
                'passing_year' => $item['passing_year'],
            ]);
        }

        $mapped = collect($created)->map(fn ($item) => [
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
        ])->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Education updated.',
            'data' => ['education' => $mapped],
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
     * Replace the research interests for the logged-in personnel.
     */
    public function updateResearchInterests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interests' => 'present|array',
            'interests.*' => 'required|string|max:255',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $profile = PersonnelProfile::firstOrCreate(['personnel_id' => $personnelId]);

        $researcher = $profile->researcherProfile;
        if (!$researcher) {
            $researcher = Researcher::create([]);
            $profile->update(['researcher_id' => $researcher->id]);
        }

        $interests = collect($validated['interests'])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        $researcher->update(['research_interests' => $interests]);

        return response()->json([
            'status' => 'success',
            'message' => 'Research interests updated.',
            'data' => ['research_interests' => $interests],
        ]);
    }

    /**
     * Replace the job experiences for the logged-in personnel.
     */
    public function updateJobExperiences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'experiences' => 'present|array',
            'experiences.*.job_title' => 'required|string|max:512',
            'experiences.*.role' => 'nullable|string|max:255',
            'experiences.*.role_description' => 'nullable|string|max:5000',
            'experiences.*.organization' => 'nullable|string|max:255',
            'experiences.*.start_date' => 'required|date',
            'experiences.*.end_date' => 'nullable|date',
        ], [
            'experiences.*.job_title.required' => 'Provide a job title.',
            'experiences.*.start_date.required' => 'Provide a start date.',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $profile = PersonnelProfile::firstOrCreate(['personnel_id' => $personnelId]);
        $profile->jobExperiences()->delete();

        $created = [];
        foreach ($validated['experiences'] as $item) {
            $created[] = PersonnelJobExperience::create([
                'personnel_id' => $personnelId,
                'job_title' => trim($item['job_title']),
                'role' => trim((string) ($item['role'] ?? '')),
                'role_description' => isset($item['role_description']) && $item['role_description'] !== '' ? trim($item['role_description']) : null,
                'organization' => isset($item['organization']) && $item['organization'] !== '' ? trim($item['organization']) : null,
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'] ?? null,
            ]);
        }

        $mapped = collect($created)->map(fn ($item) => [
            'job_title' => $item->job_title,
            'role' => $item->role,
            'role_description' => $item->role_description,
            'organization' => $item->organization,
            'start_date' => optional($item->start_date)->toDateString(),
            'end_date' => optional($item->end_date)->toDateString(),
        ])->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Job experiences updated.',
            'data' => ['job_experiences' => $mapped],
        ]);
    }

    /**
     * Replace the training/workshop/seminar entries of one type for the logged-in personnel.
     */
    public function updateTrainingSeminars(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:Seminar,Workshop,Training',
            'items' => 'present|array',
            'items.*.title' => 'required|string|max:2000',
            'items.*.attendee_type' => 'nullable|in:Organizer,Keynote speaker,Speaker,Presenter,Panel discussant,Trainer,Trainee,Conductor,Attendee',
            'items.*.excerpt' => 'nullable|string|max:2000',
            'items.*.description' => 'nullable|string|max:5000',
            'items.*.start_date' => 'required|date',
            'items.*.end_date' => 'nullable|date',
        ], [
            'items.*.title.required' => 'Provide a title.',
            'items.*.start_date.required' => 'Provide a start date.',
        ]);

        $imsUser = $this->readImsUser($request);
        $personnelId = $imsUser['personnel_id'] ?? null;

        if (!$personnelId) {
            return response()->json(['status' => 'error', 'message' => 'Could not identify personnel.'], 403);
        }

        $type = $validated['type'];

        // Replace only this type's rows, leaving other types untouched
        SeminarWorkshopTraining::where('personnel_id', $personnelId)
            ->where('type', $type)
            ->delete();

        foreach ($validated['items'] as $item) {
            SeminarWorkshopTraining::create([
                'personnel_id' => $personnelId,
                'type' => $type,
                'title' => trim($item['title']),
                'attendee_type' => $item['attendee_type'] ?? 'Attendee',
                'excerpt' => isset($item['excerpt']) && $item['excerpt'] !== '' ? trim($item['excerpt']) : null,
                'description' => isset($item['description']) && $item['description'] !== '' ? trim($item['description']) : '',
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'] ?? null,
            ]);
        }

        // Return all rows (all types) so the client can refresh its local copy
        $all = SeminarWorkshopTraining::where('personnel_id', $personnelId)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'attendee_type' => $item->attendee_type,
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'description' => $item->description,
                'start_date' => optional($item->start_date)->toDateString(),
                'end_date' => optional($item->end_date)->toDateString(),
            ])->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Updated.',
            'data' => ['training_seminars' => $all],
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
