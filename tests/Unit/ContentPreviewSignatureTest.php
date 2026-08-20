<?php

namespace Tests\Unit;

use App\Support\ContentPreviewSignature;
use Tests\TestCase;

class ContentPreviewSignatureTest extends TestCase
{
    public function test_it_accepts_only_matching_unexpired_signatures(): void
    {
        config()->set('content_preview.secret', 'test-preview-secret');
        $expires = now()->addMinutes(5)->timestamp;
        $signature = ContentPreviewSignature::make('cste', 8, $expires);

        $this->assertTrue(ContentPreviewSignature::isValid('cste', 8, $expires, $signature));
        $this->assertFalse(ContentPreviewSignature::isValid('cste', 9, $expires, $signature));
        $this->assertFalse(ContentPreviewSignature::isValid('cste', 8, now()->subMinute()->timestamp, $signature));
    }
}
