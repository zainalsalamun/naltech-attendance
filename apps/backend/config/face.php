<?php

return [
    // The preview is deliberately not an attendance verifier or liveness proof.
    'preview_enabled' => (bool) env('FACE_MATCHING_PREVIEW_ENABLED', false),
    'provider' => env('FACE_PROVIDER', 'none'),
    'self_hosted_url' => env('FACE_SELF_HOSTED_URL', 'http://127.0.0.1:8021'),
    'self_hosted_key' => env('FACE_SELF_HOSTED_KEY'),
    // Must be calibrated before production use; this value is for PoC only.
    'preview_similarity_threshold' => 0.5,
    'pending_retention_days' => 30,
];
