<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS'])),
    'cookie_samesite' => 'Strict',
]);
header('Content-Type: application/json; charset=UTF-8');

if (isset($_SESSION['user'])) {
    // Check inactivity timeout (15 minutes = 900 seconds)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(['logged_in' => false, 'error' => 'Sesión expirada por inactividad']);
        exit;
    }
    
    // Update activity timestamp
    $_SESSION['last_activity'] = time();
    
    $username = $_SESSION['user']['username'];
    $role = $_SESSION['user']['role'] ?? ($username === 'admin' ? 'admin' : 'staff');

    echo json_encode([
        'logged_in' => true,
        'user' => [
            'username' => $username,
            'name' => $_SESSION['user']['name'],
            'role' => $role
        ]
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
