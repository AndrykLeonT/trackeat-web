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
    // Módulo 2 · Límites de negocio al crear un pedido (T-07, superficie A08). Los aplica PedidoService.
    'pedidos' => [
        'cantidad_max' => (int) env('TRACKEAT_CANTIDAD_MAX', 100),          // piezas de un producto en un renglón
        'renglones_max' => (int) env('TRACKEAT_RENGLONES_MAX', 50),         // renglones (productos) por pedido
        'total_max_cliente' => (string) env('TRACKEAT_TOTAL_MAX_CLIENTE', '5000.00'),    // MXN, pedido web
        'total_max_personal' => (string) env('TRACKEAT_TOTAL_MAX_PERSONAL', '20000.00'), // MXN, capturado por personal
        'mesas_max' => (int) env('TRACKEAT_MESAS_MAX', 30),                  // mesas numeradas de 1 a este valor
        'abiertos_max_cliente' => (int) env('TRACKEAT_ABIERTOS_MAX_CLIENTE', 3), // pedidos en curso por cliente
        // HU-05: motivos que el personal elige al cancelar (máx. 50 caracteres, la longitud de la columna).
        'motivos_cancelacion' => ['Cliente se retiró', 'Error en la captura', 'Producto agotado', 'Pedido duplicado', 'Demora excesiva', 'Otro'],
        // Lo que se guarda cuando cancela el propio cliente (a él no se le pide motivo).
        'motivo_cliente' => 'Cancelado por el cliente',
    ],
];
