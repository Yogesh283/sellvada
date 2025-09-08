<?php
return [
    "default" => env("FILESYSTEM_DRIVER", "local"),
    "disks" => [
        "local" => [
            "driver" => "local",
            "root" => sys_get_temp_dir(),
        ],
    ],
    "links" => [],
];