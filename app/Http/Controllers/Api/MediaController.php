<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Upload an image and return the public URL
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp,svg|max:5120', // 5MB max
            'folder' => 'string|nullable', // Optional: pages, posts, profiles, etc.
        ]);

        try {
            $image = $request->file('image');
            $folder = $request->input('folder', 'uploads');
            
            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            // Store in public disk under the specified folder
            $path = $image->storeAs("images/{$folder}", $filename, 'public');
            
            // Get public URL
            $url = Storage::disk('public')->url($path);
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => $url,
                    'full_url' => config('app.url') . $url, // Absolute URL
                    'size' => $image->getSize(),
                    'mime_type' => $image->getMimeType(),
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an uploaded image
     */
    public function deleteImage(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');
            
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
