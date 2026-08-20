<?php

namespace App\Support;

class ContentPreviewSignature
{
    public static function make(string $entitySlug, int $postId, int $expires): string
    {
        return hash_hmac('sha256', self::payload($entitySlug, $postId, $expires), self::secret());
    }

    public static function isValid(string $entitySlug, int $postId, int $expires, string $signature): bool
    {
        if ($expires < now()->timestamp || $signature === '') {
            return false;
        }

        return hash_equals(self::make($entitySlug, $postId, $expires), $signature);
    }

    private static function payload(string $entitySlug, int $postId, int $expires): string
    {
        return strtolower(trim($entitySlug)).'|'.$postId.'|'.$expires;
    }

    private static function secret(): string
    {
        $secret = (string) config('content_preview.secret');

        if ($secret === '') {
            throw new \RuntimeException('UAS content preview secret is not configured.');
        }

        return $secret;
    }
}
