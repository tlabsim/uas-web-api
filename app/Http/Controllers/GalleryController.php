<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->filled('entity_id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id'], 400);
        }

        $query = Gallery::published()
            ->with(['coverMediaItem'])
            ->withCount('items')
            ->where('owner_entity_id', $request->integer('entity_id'))
            ->orderByDesc('published_at');

        if ($request->filled('is_featured')) {
            $query->where('is_featured', filter_var($request->input('is_featured'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('include_items')) {
            $query->with(['items.mediaItem']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($request->input('limit', 12)),
        ]);
    }

    public function show(Request $request)
    {
        if (!$request->filled('id') && !($request->filled('entity_id') && $request->filled('slug'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing gallery id or entity_id + slug',
            ], 400);
        }

        $query = Gallery::published()->with(['coverMediaItem', 'items.mediaItem']);

        if ($request->filled('id')) {
            $query->where('id', $request->integer('id'));
        } else {
            $query->where('owner_entity_id', $request->integer('entity_id'))
                ->where('slug', $request->input('slug'));
        }

        $gallery = $query->first();

        if (!$gallery) {
            return response()->json(['status' => 'error', 'message' => 'Gallery not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $gallery]);
    }
}
