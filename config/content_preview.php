<?php

return [
    'secret' => env('UAS_CONTENT_PREVIEW_SECRET'),
    'ttl_minutes' => (int) env('UAS_CONTENT_PREVIEW_TTL_MINUTES', 15),
];
