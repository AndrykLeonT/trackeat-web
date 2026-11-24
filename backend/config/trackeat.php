<?php

return [
    'demo_enabled' => (bool) env('TRACKEAT_DEMO_ENABLED', false),
    // Browser endpoint only. Laravel publishes directly to the local Reverb server.
    'socket_url' => env('TRACKEAT_SOCKET_URL', 'http://localhost:8080'),
    // Nombre del nodo detrás del balanceador (cabecera X-Servidor y /salud).
    'nodo' => env('NODO', gethostname()),
    // Zona horaria que decide cuándo empieza el día para el folio consecutivo de los pedidos. Es la del
    // restaurante (Mazatlán), no APP_TIMEZONE: cambiar esa afectaría todas las fechas del sistema.
    'zona_folio' => env('TRACKEAT_ZONA_FOLIO', 'America/Mazatlan'),
];
