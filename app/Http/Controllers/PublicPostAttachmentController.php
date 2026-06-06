<?php

namespace App\Http\Controllers;

use App\Models\PostAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicPostAttachmentController extends Controller
{
    public function show(Request $request, string $publicKey, ?string $filename = null)
    {
        $attachment = PostAttachment::where('public_key', $publicKey)->firstOrFail();

        $path = $attachment->attachment_uri;
        abort_unless($path, 404);

        $cacheMaxAge = max(60, (int) config('media.cache_max_age', 604800));
        $headers = [
            'Cache-Control' => "public, max-age={$cacheMaxAge}, immutable",
            'X-Robots-Tag' => 'noindex',
        ];

        if ($attachment->mime_type) {
            $headers['Content-Type'] = $attachment->mime_type;
        }

        $directUrl = $attachment->directUrl();
        if (config('media.proxy_delivery_mode', 'stream') === 'redirect' && $directUrl) {
            $response = redirect()->away($directUrl);
            $response->headers->add($headers);

            return $response;
        }

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response(
            $path,
            $attachment->publicFileName(),
            $headers,
            'inline'
        );
    }
}
