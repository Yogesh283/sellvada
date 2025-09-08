<?php
return [
    "default" => env("CACHE_DRIVER", "file"),
    "stores" => [
        "file" => [
            "driver" => "file",
            "path" => sys_get_temp_dir() . DIRECTORY_SEPARATOR . "laravel_cache",
        ],
    ],
    "prefix" => env("CACHE_PREFIX", "laravel_cache"),
];