<?php
// URL del dashboard MUHU que recibe los pedidos
define('MUHU_BASE_URL', 'https://ops.muhucafeteria.com');

// Token de ingesta — EL MISMO valor que el secret OPS_INGEST_TOKEN de MUHU.
define('OPS_INGEST_TOKEN', 'CAMBIAR_ESTE_TOKEN');

// Usuarios trabajadores de ESTA app (hash bcrypt).
$PEDIDOS_USERS = [
    'trabajador' => [
        'hash' => '$2y$12$YOUR_HASH_HERE',
        'name' => 'Trabajador Cocina'
    ],
];
