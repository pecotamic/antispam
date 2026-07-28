<?php

return [
    'forms' => ['*'],
    'minimum_fill_time' => 5,
    'maximum_fill_time' => 7200,
    'cookie' => [
        'name' => 'statamic_form_started_at',
        'same_site' => 'Lax',
    ],
    'patterns' => [],
];
