<?php

$ids = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [
    'seat_data_path' => database_path('data/volkshaus-theatersaal-seats.json'),

    'event_id' => env('HIEVENTS_EVENT_ID'),
    'seated_product_ids' => $ids('SEATED_PRODUCT_IDS'),
    'wheelchair_question_ids' => $ids('WHEELCHAIR_QUESTION_IDS'),
    'wheelchair_notify_emails' => $ids('WHEELCHAIR_NOTIFY_EMAILS'),

    'hievents' => [
        'base_url' => env('HIEVENTS_BASE_URL'),
        'api_key' => env('HIEVENTS_API_KEY'),
        'webhook_secret' => env('HIEVENTS_WEBHOOK_SECRET'),
    ],

    'event_name' => env('EVENT_NAME'),
    'event_date' => env('EVENT_DATE'),
    'doors_at' => env('DOORS_AT'),
    'selection_opens_at' => env('SELECTION_OPENS_AT'),
    'selection_deadline' => env('SELECTION_DEADLINE'),
    // Best seats first. Unlisted sections come last.
    'section_preference' => ['parterre', 'galerie-mitte', 'galerie-links', 'galerie-rechts'],

    'reminder_offsets_days' => array_map('intval', $ids('REMINDER_OFFSETS_DAYS') ?: [14, 7, 2]),
    'final_mail_offset_days' => (int) env('FINAL_MAIL_OFFSET_DAYS', 2),
    'mail_per_second' => (int) env('MAIL_PER_SECOND', 5),
    'contact_email' => env('CONTACT_EMAIL'),

    'admin_emails' => $ids('ADMIN_EMAILS'),
    'mail_failure_threshold' => (int) env('MAIL_FAILURE_THRESHOLD', 5),

    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
