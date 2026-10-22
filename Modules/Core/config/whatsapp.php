<?php

return [
    // Meta App Dashboard > App settings > Basic > App Secret.
    'app_secret' => env('META_WHATSAPP_APP_SECRET'),
    // Arbitrary high-entropy value entered in Meta's webhook configuration.
    'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
];
