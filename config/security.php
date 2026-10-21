<?php

return [
    // Strict-Transport-Security (enable once the site is served exclusively over HTTPS)
    'hsts' => env('SECURITY_HSTS', false),

    // Content-Security-Policy (disabled automatically in the local environment for the Vite dev server)
    'csp' => env('SECURITY_CSP', true),

    // Bearer token Vercel Cron Jobs send to /cron/maintenance (endpoint is disabled when empty)
    'cron_secret' => env('CRON_SECRET'),
];
