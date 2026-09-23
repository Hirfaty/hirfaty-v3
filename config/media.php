<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | Shared by the product media gallery (photos/videos) and the seller
    | shop logo/banner uploads. Keep the frontend copies of these numbers
    | (resources/js/lib/media-limits.ts) in sync manually — there is no
    | shared codegen between PHP config and TypeScript here, so the
    | frontend check exists purely to fail fast; this file is the
    | enforced source of truth.
    |
    | Sizes are in kilobytes (Laravel validation convention). Dimensions
    | are generous enough to accept an unedited modern phone photo
    | (e.g. a 4032x3024 iPhone shot) while rejecting raw scans/exports
    | far beyond anything a product photo needs.
    |
    */

    'image' => [
        'max_kb' => 5120, // 5 MB
        'max_width' => 4096,
        'max_height' => 4096,
    ],

    // Product photos (gallery and variants) are stricter than the other image
    // uploads: the admin UI crops and compresses them under this ceiling
    // before upload (resources/js/lib/crop-image.ts), and the server enforces
    // it for anything that bypasses the UI.
    'product_image' => [
        'max_kb' => 1024, // 1 MB
    ],

    'video' => [
        'max_kb' => 51200, // 50 MB — video naturally needs more headroom
    ],

];
