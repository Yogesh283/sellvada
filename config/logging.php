<?php
return [
    "default" => env("LOG_CHANNEL", "stack"),
    "channels" => [
        "single" => [
            "driver" => "single",
            "path" => sys_get_temp_dir() . DIRECTORY_SEPARATOR . "laravel.log",
            "level" => env("LOG_LEVEL", "debug"),
        ],
        "stack" => [
            "driver" => "stack",
            "channels" => ["single"],
            "ignore_exceptions" => false,
        ],
    ],
];