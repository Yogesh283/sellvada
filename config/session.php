<?php
return [
    "driver" => env("SESSION_DRIVER", "file"),
    "lifetime" => env("SESSION_LIFETIME", 120),
    "expire_on_close" => false,
    "encrypt" => false,
    "files" => sys_get_temp_dir(),
    "cookie" => env("SESSION_COOKIE", "laravel_session"),
    "path" => "/",
    "domain" => env("SESSION_DOMAIN", null),
    "secure" => env("SESSION_SECURE_COOKIE", false),
    "http_only" => true,
    "same_site" => "lax",
];