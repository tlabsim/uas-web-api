<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Models\EntityWebSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $entityId = $request->input('entity_id');

        if (!$entityId) {
            return response()->json([
                'success' => false,
                'message' => 'Entity ID is required.',
            ], 400);
        }

        try {
            $settings = EntityWebSetting::where('entity_id', $entityId)
                ->orderBy('key_group')
                ->orderBy('setting_key')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching entity settings', [
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch entity settings.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity_id' => 'required|integer',
            'key_group' => 'required|string|max:50',
            'setting_key' => 'required|string|max:100',
            'value' => 'nullable',
            'value_type' => 'required|in:string,int,float,bool,json',
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
            // Check if setting already exists
            $exists = EntityWebSetting::where('entity_id', $entityId)
                ->where('key_group', $validated['key_group'])
                ->where('setting_key', $validated['setting_key'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A setting with this key already exists in this group.',
                ], 422);
            }

            // Process value based on type
            $value = $this->processValue($validated['value'] ?? '', $validated['value_type']);

            $setting = EntityWebSetting::create([
                'entity_id' => $entityId,
                'key_group' => $validated['key_group'],
                'setting_key' => $validated['setting_key'],
                'value' => $value,
                'value_type' => $validated['value_type'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting created successfully.',
                'data' => $setting,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating entity setting', [
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create entity setting.',
            ], 500);
        }
    }

    public function updateSingle(Request $request, $id)
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => 'required|integer',
            'entity_id' => 'required|integer',
            'key_group' => 'required|string|max:50',
            'value' => 'nullable',
            'value_type' => 'required|in:string,int,float,bool,json',
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
            $setting = EntityWebSetting::where('id', $id)
                ->where('entity_id', $entityId)
                ->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found.',
                ], 404);
            }

            // Process value based on type
            $value = $this->processValue($validated['value'] ?? '', $validated['value_type']);

            $setting->update([
                'key_group' => $validated['key_group'],
                'value' => $value,
                'value_type' => $validated['value_type'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully.',
                'data' => $setting,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating entity setting', [
                'id' => $id,
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update entity setting.',
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $entityId = $request->input('entity_id');

        if (!$entityId) {
            return response()->json([
                'success' => false,
                'message' => 'Entity ID is required.',
            ], 400);
        }

        try {
            $setting = EntityWebSetting::where('id', $id)
                ->where('entity_id', $entityId)
                ->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found.',
                ], 404);
            }

            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted successfully.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting entity setting', [
                'id' => $id,
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete entity setting.',
            ], 500);
        }
    }

    // Keep the bulk update method for backward compatibility
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity_id' => 'required|integer',
            'settings' => 'required|array',
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
        $settings = $validated['settings'];

        try {
            // Process each setting group
            foreach ($settings as $keyGroup => $groupSettings) {
                foreach ($groupSettings as $settingKey => $value) {
                    // Determine value type
                    $valueType = $this->determineValueType($value);

                    // Update or create setting
                    EntityWebSetting::updateOrCreate(
                        [
                            'entity_id' => $entityId,
                            'key_group' => $keyGroup,
                            'setting_key' => $settingKey,
                        ],
                        [
                            'value' => is_array($value) ? json_encode($value) : $value,
                            'value_type' => $valueType,
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Entity settings updated successfully.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating entity settings', [
                'entity_id' => $entityId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update entity settings.',
            ], 500);
        }
    }

    /**
     * Process value based on type
     */
    private function processValue($value, string $type)
    {
        switch ($type) {
            case 'bool':
                return $value ? '1' : '0';
            case 'json':
                return is_array($value) ? json_encode($value) : $value;
            default:
                return $value;
        }
    }

    /**
     * Determine the value type for a setting
     */
    private function determineValueType($value): string
    {
        if (is_array($value)) {
            return 'json';
        }
        
        if (is_bool($value) || $value === 'true' || $value === 'false' || $value === '1' || $value === '0') {
            return 'bool';
        }
        
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? 'float' : 'int';
        }
        
        return 'string';
    }
}
