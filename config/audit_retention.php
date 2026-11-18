<?php

return [
    // A future approved cleanup implementation must still enforce holds and authorization.
    'enabled' => env('AUDIT_RETENTION_ENABLED', false),
    'days' => env('AUDIT_RETENTION_DAYS'),
];
