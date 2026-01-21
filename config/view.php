<?php

return [
    'paths' => [
        resource_path('themes'),
        base_path('modules'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),
];
