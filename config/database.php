<?php
return [
    "default" => env("DB_CONNECTION", "sqlite"),
    "connections" => [
        "sqlite" => [
            "driver" => "sqlite",
            "database" => env("DB_DATABASE", ":memory:"),
            "prefix" => "",
            "foreign_key_constraints" => env("DB_FOREIGN_KEYS", true),
        ],
    ],
    "migrations" => "migrations",
];