<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    | Daleel Qatar media disks. "media" holds publicly viewable images,
    | "private_media" holds documents that must never be publicly listed.
    */
    'media_disk' => env('MEDIA_DISK', 'public'),
    'private_media_disk' => env('PRIVATE_MEDIA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Supabase Storage — public bucket (logos, covers, galleries, offer images)
        'supabase' => [
            'driver' => 'supabase',
            'url' => env('SUPABASE_URL'),
            'key' => env('SUPABASE_SERVICE_ROLE_KEY'),
            'bucket' => env('SUPABASE_PUBLIC_BUCKET', 'daleel-public'),
            'public' => true,
            'signed_url_ttl' => (int) env('SUPABASE_SIGNED_URL_TTL', 600),
            'throw' => true,
        ],

        // Supabase Storage — private bucket (verification documents, claim proofs).
        // Files are only reachable through short-lived signed URLs.
        'supabase_private' => [
            'driver' => 'supabase',
            'url' => env('SUPABASE_URL'),
            'key' => env('SUPABASE_SERVICE_ROLE_KEY'),
            'bucket' => env('SUPABASE_PRIVATE_BUCKET', 'daleel-private'),
            'public' => false,
            'signed_url_ttl' => (int) env('SUPABASE_SIGNED_URL_TTL', 600),
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
