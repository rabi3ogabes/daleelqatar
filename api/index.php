<?php

/*
|--------------------------------------------------------------------------
| Vercel serverless entry point
|--------------------------------------------------------------------------
| Vercel runs this file (vercel-php runtime) for every request that is not
| a static file in public/. The deployment bundle is read-only, so writable
| paths move to /tmp and defaults suited to a stateless function are set
| here. Anything configured in the Vercel project settings wins.
*/

$defaults = [
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'APP_CONFIG_CACHE' => '/tmp/bootstrap/config.php',
    'APP_EVENTS_CACHE' => '/tmp/bootstrap/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/bootstrap/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/bootstrap/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/bootstrap/services.php',
    'LOG_CHANNEL' => 'stderr',
    // One line per entry, without stack traces: Vercel truncates long log
    // entries, which would otherwise cut off the error message itself.
    'LOG_STDERR_FORMATTER' => 'Monolog\\Formatter\\LineFormatter',
    // No background worker exists on Vercel: run queued work inline.
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'database',
    'SESSION_SECURE_COOKIE' => 'true',
    'CACHE_STORE' => 'database',
    // Local disks do not survive between invocations: store media in Supabase.
    'MEDIA_DISK' => 'supabase',
    'PRIVATE_MEDIA_DISK' => 'supabase_private',
    'DB_SSLMODE' => 'require',
    'DB_SEARCH_PATH' => 'daleel,extensions,public',
];

if (getenv('APP_URL') === false && ($host = getenv('VERCEL_PROJECT_PRODUCTION_URL')) !== false) {
    $defaults['APP_URL'] = 'https://'.$host;
}

foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

$storage = getenv('LARAVEL_STORAGE_PATH');
$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/private', 'app/public'] as $dir) {
    is_dir("{$storage}/{$dir}") || @mkdir("{$storage}/{$dir}", 0755, true);
}
is_dir(dirname(getenv('APP_PACKAGES_CACHE'))) || @mkdir(dirname(getenv('APP_PACKAGES_CACHE')), 0755, true);

// The built-in server reports whichever bundle file a URL happens to match
// (e.g. /composer.json) as the running script; Laravel must always see itself
// as public/index.php so those URLs route (and 404) normally.
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
