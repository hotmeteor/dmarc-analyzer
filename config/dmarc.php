<?php

return [

    'mailboxes' => [

        'default' => [
            'name' => env('DMARC_MAILBOX_NAME'),
            'host' => env('DMARC_MAILBOX_HOST'),
            'username' => env('DMARC_MAILBOX_USERNAME'),
            'password' => env('DMARC_MAILBOX_PASSWORD'),
            'mailbox' => env('DMARC_MAILBOX_MAILBOX', 'INBOX'),
            'encryption' => env('DMARC_MAILBOX_ENCRYPTION', 'ssl'),
            'novalidate_cert' => false,
        ],

    ],

    'directories' => [

//        'default' => [
//
//        ],

    ],

    'fetcher' => [

        'mailboxes' => [
            'done' => '',
            'fail' => '',
            'max_messages' => 50,
        ],

        'directories' => [
            'done' => '',
            'fail' => '',
            'max_messages' => 50,
        ],

    ]

];
