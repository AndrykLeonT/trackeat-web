<?php

return [
    'demo_enabled' => (bool) env('TRACKEAT_DEMO_ENABLED', false),
    // Browser endpoint only. Laravel publishes directly to the local Reverb server.
    'socket_url' => env('TRACKEAT_SOCKET_URL', 'http://localhost:8080'),
];
