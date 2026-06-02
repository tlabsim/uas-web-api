<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Snippet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SnippetController extends Controller
{
    public function index(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $snippets = Snippet::with('meta')
            ->where('entity_id', $entityId)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $snippets]);
    }

    public function store(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:240',
            'slug' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('snippets', 'slug'),
            ],
            'snippet_group' => 'nullable|string|max:240',
            'content' => 'nullable|string',
            'css' => 'nullable|string',
            'js' => 'nullable|string',
            'tags' => 'nullable|string|max:65535',
            'status' => 'required|in:Draft,Published',
        ]);

        $snippet = Snippet::create([
            'entity_id' => $entityId,
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'snippet_group' => $validated['snippet_group'] ?? null,
            'content' => $validated['content'] ?? '',
            'tags' => $validated['tags'] ?? null,
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'Published' ? now() : null,
            'content_last_edited_at' => now(),
        ]);

        $this->syncMeta($snippet, [
            'css' => $validated['css'] ?? '',
            'js' => $validated['js'] ?? '',
        ]);

        $snippet->load('meta');

        return response()->json([
            'status' => 'success',
            'message' => 'Snippet created successfully.',
            'data' => $snippet,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $snippet = Snippet::where('entity_id', $entityId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:240',
            'slug' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('snippets', 'slug')->ignore($snippet->id),
            ],
            'snippet_group' => 'nullable|string|max:240',
            'content' => 'nullable|string',
            'css' => 'nullable|string',
            'js' => 'nullable|string',
            'tags' => 'nullable|string|max:65535',
            'status' => 'required|in:Draft,Published',
        ]);

        $snippet->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'snippet_group' => $validated['snippet_group'] ?? null,
            'content' => $validated['content'] ?? '',
            'tags' => $validated['tags'] ?? null,
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'Published'
                ? ($snippet->published_at ?? now())
                : null,
            'content_last_edited_at' => now(),
        ]);

        $this->syncMeta($snippet, [
            'css' => $validated['css'] ?? '',
            'js' => $validated['js'] ?? '',
        ]);

        $snippet->load('meta');

        return response()->json([
            'status' => 'success',
            'message' => 'Snippet updated successfully.',
            'data' => $snippet,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $snippet = Snippet::where('entity_id', $entityId)->findOrFail($id);
        $snippet->delete();

        return response()->json(['status' => 'success', 'message' => 'Snippet deleted successfully.']);
    }

    protected function syncMeta(Snippet $snippet, array $meta): void
    {
        foreach ($meta as $key => $value) {
            $existing = $snippet->meta()->where('meta_key', $key)->first();

            if ($existing) {
                $existing->update([
                    'meta_value' => (string) $value,
                    'value_type' => 'string',
                ]);
                continue;
            }

            $snippet->meta()->create([
                'meta_key' => $key,
                'meta_value' => (string) $value,
                'value_type' => 'string',
            ]);
        }
    }
}
