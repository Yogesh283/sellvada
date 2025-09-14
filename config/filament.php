<?php

return [

    'path' => 'admin',   // ✅ यह जोड़ो

    'broadcasting' => [
        // ...
    ],

    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

    'assets_path' => 'filament',  // ✅ यह सही करो

    'cache_path' => base_path('bootstrap/cache/filament'),

    'livewire_loading_delay' => 'default',

    'system_route_prefix' => 'filament',
];
