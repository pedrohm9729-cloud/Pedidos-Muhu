<?php
// URL del dashboard MUHU que recibe los pedidos
define('MUHU_BASE_URL', 'https://ops.muhucafeteria.com');

// Token de ingesta — EL MISMO valor que el secret OPS_INGEST_TOKEN de MUHU.
define('OPS_INGEST_TOKEN', 'CAMBIAR_ESTE_TOKEN');

// Usuarios de ESTA app (hash bcrypt).
//   role: 'admin'  -> panel de gestión de pedidos en curso.
//   role: 'staff'  -> pantalla para crear pedidos (por defecto).
// Si se omite 'role', el usuario 'admin' se trata como administrador y
// cualquier otro como personal (staff).
$PEDIDOS_USERS = [
    'admin' => [
        'hash' => '$2y$12$YOUR_ADMIN_HASH_HERE',
        'name' => 'Administrador',
        'role' => 'admin'
    ],
    'sandra' => [
        'hash' => '$2y$12$YOUR_STAFF_HASH_HERE',
        'name' => 'Sandra',
        'role' => 'staff'
    ],
    'trabajador' => [
        'hash' => '$2y$12$YOUR_HASH_HERE',
        'name' => 'Trabajador Cocina',
        'role' => 'staff'
    ],
];
