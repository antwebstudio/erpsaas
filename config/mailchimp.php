<?php

return [
    'api_key' => env('MAILCHIMP_API_KEY'),
    'server_prefix' => env('MAILCHIMP_SERVER_PREFIX', 'us1'),
    'lists' => [
        'contacts' => env('MAILCHIMP_CONTACTS_LIST_ID'),
    ],
];
