<?php

return [
    // Never send mail inline during checkout, even when the general queue uses sync.
    'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
    'queue' => env('NOTIFICATION_QUEUE', 'default'),
];
