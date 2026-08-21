<?php

namespace App\Http\Controllers;

use App\Models\EntityProfile;
use App\Models\Post;
use App\Support\ContentPreviewSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostPreviewController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_slug' => ['required', 'string', 'max:50', 'alpha_dash'],
            'post_id' => ['required', 'integer', 'min:1'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64'],
        ]);

        if (!ContentPreviewSignature::isValid(
            $validated['entity_slug'],
            (int) $validated['post_id'],
            (int) $validated['expires'],
            $validated['signature']
        )) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired preview link.'], 403);
        }

        $entity = EntityProfile::where('slug', $validated['entity_slug'])->first();
        if (!$entity) {
            return response()->json(['status' => 'error', 'message' => 'Entity not found.'], 404);
        }

        $post = Post::with(['attachments', 'metadata', 'taggedEntities'])
            ->find((int) $validated['post_id']);

        $belongsToEntity = $post && (
            (int) $post->owner_entity_id === (int) $entity->entity_id
            || $post->taggedEntities->contains(
                fn ($tag) => (int) $tag->entity_id === (int) $entity->entity_id
                    && $tag->status === 'Approved'
            )
        );

        if (!$belongsToEntity) {
            return response()->json(['status' => 'error', 'message' => 'Post not found.'], 404);
        }

        $post->setAttribute('metadata_values', $post->metadata->pluck('meta_value', 'meta_key')->all());

        return response()->json(['status' => 'success', 'data' => $post]);
    }
}
