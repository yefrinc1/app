<?php

return [
    'token_hours' => (int) env('PORTAL_CLIENTE_TOKEN_HOURS', 168),

    'tutoriales' => [
        'primaria_ps4' => env('PORTAL_TUTORIAL_PRIMARIA_PS4'),
        'primaria_ps5' => env('PORTAL_TUTORIAL_PRIMARIA_PS5'),
        'secundaria_ps4' => env('PORTAL_TUTORIAL_SECUNDARIA_PS4'),
        'secundaria_ps5' => env('PORTAL_TUTORIAL_SECUNDARIA_PS5'),
    ],
];
