<?php

return [
    'types' => [
        'portal',
        'campaign',
        'evaluation',
        'anonymous',
    ],

    'storage' => [
        'disk' => env('QR_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'directory' => env('QR_STORAGE_DIRECTORY', 'qr-codes'),
        'svg_extension' => 'svg',
        'pdf_extension' => 'pdf',
        'default_size' => 512,
    ],
];
