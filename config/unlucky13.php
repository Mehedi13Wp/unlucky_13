<?php

return [
    'worker_token' => env('UNLUCKY13_WORKER_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Delivery dry-run
    |--------------------------------------------------------------------------
    |
    | When enabled, sender-account delivery uses Laravel's log mail transport
    | instead of opening a real SMTP connection. Database state still follows
    | the normal success path, so use dry-run only with disposable test data.
    |
    */
    'delivery_dry_run' => env('UNLUCKY13_DELIVERY_DRY_RUN', false),
];
