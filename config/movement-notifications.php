<?php

return [
    'connection' => env('MOVEMENT_APPROVAL_QUEUE_CONNECTION', 'database'),
    'queue' => env('MOVEMENT_APPROVAL_QUEUE', 'approval-mail'),
];
