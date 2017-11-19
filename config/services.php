<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, Mandrill, and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'mandrill' => [
        'secret' => '',
    ],

    'ses' => [
        'key'    => '',
        'secret' => '',
        'region' => 'us-east-1',
    ],

    'stripe' => [
        'model'  => 'User',
        'secret' => '',
    ],

    'twitter' => [
        'widget_id' => env('TWITTER_WIDGET_ID'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET')
    ],

    'facebook' => [
      'client_id' => '871539976331050',
      'client_secret' => 'e43c95d182bdb633fed20d4b00e602d6',
      'redirect' => 'http://www.babsonyoga.com/login/callback',
    ]
];
