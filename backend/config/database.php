<?php

return [
    'default' => 'census',
    'connections' => [
        'census' => [
            'driver' => 'sqlite',
            'database' => env('CENSUS_DATABASE_PATH', base_path('../censo.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
            'options' => [
                PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
            ],
        ],
    ],
];
