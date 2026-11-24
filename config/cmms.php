<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project settings
    |--------------------------------------------------------------------------
    |
    | Values that are specific to the Shipyard CMMS assignment. Secrets such as
    | the seed password must only ever live in the untracked .env file.
    |
    */

    'student_id' => env('CMMS_STUDENT_ID', 'S79244'),

    'test_date' => env('CMMS_TEST_DATE', '2026-12-02'),

    'seed_password' => env('CMMS_SEED_PASSWORD'),

];
