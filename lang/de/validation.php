<?php

// Only what the admin forms can trigger. Filament brings everything else.
return [
    'required' => 'Bitte ausfüllen.',
    'max' => [
        'string' => 'Maximal :max Zeichen.',
        'array' => 'Maximal :max Einträge.',
    ],
];
