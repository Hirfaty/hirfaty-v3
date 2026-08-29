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

    'default' => env('FILESYSTEM_DISK', 'public'),

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

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => false,
        ],

        /*
         * SeaweedFS exposes an S3-compatible gateway, so it reuses the s3
         * driver. Two settings differ from AWS and matter:
         *  - `endpoint` must point at the SeaweedFS S3 gateway (default :8333)
         *  - path-style addressing is required; SeaweedFS does not serve
         *    virtual-host style buckets (bucket.host/key)
         *
         * Switch over with FILESYSTEM_DISK=seaweedfs — no code changes needed,
         * because uploads resolve the default disk at runtime.
         */
        'seaweedfs' => [
            'driver' => 's3',
            'key' => env('SEAWEEDFS_ACCESS_KEY_ID'),
            'secret' => env('SEAWEEDFS_SECRET_ACCESS_KEY'),
            'region' => env('SEAWEEDFS_REGION', 'us-east-1'),
            'bucket' => env('SEAWEEDFS_BUCKET', 'hirfaty'),
            // Public base URL used to build image links — typically a filer or
            // CDN in front of SeaweedFS.
            'url' => env('SEAWEEDFS_URL'),
            'endpoint' => env('SEAWEEDFS_ENDPOINT', 'http://127.0.0.1:8333'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => false,
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
