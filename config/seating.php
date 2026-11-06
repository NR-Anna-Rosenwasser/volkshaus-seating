<?php

$ids = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [
    'seat_data_path' => database_path('data/volkshaus-theatersaal-seats.json'),

    'hievents' => [
        'base_url' => env('HIEVENTS_BASE_URL'),
        'api_key' => env('HIEVENTS_API_KEY'),
        'webhook_secret' => env('HIEVENTS_WEBHOOK_SECRET'),
    ],

    'mail_per_second' => (int) env('MAIL_PER_SECOND', 5),

    'backup_path' => env('BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    'admin_emails' => $ids('ADMIN_EMAILS'),
    'mail_failure_threshold' => (int) env('MAIL_FAILURE_THRESHOLD', 5),

    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
