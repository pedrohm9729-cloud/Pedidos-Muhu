<?php
require_once __DIR__ . '/config.php';

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS'])),
    'cookie_samesite' => 'Strict',
]);

header('Content-Type: application/json; charset=UTF-8');

// Exigir sesión activa (trabajador logueado en ESTA app)
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión requerida']);
    exit;
}

// Inactivity timeout validation (15 minutes = 900 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(['error' => 'Sesión expirada por inactividad']);
    exit;
}
$_SESSION['last_activity'] = time();

$action = $_GET['action'] ?? '';

/**
 * Perform a cURL request to MUHU API
 */
function muhu_call(string $method, string $path, ?array $body = null): array {
    $url = rtrim(MUHU_BASE_URL, '/') . $path;
    $ch = curl_init($url);
    
    $headers = [
        'X-Ingest-Token: ' . OPS_INGEST_TOKEN,
        'Accept: application/json'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    
    curl_close($ch);
    
    if ($resp === false) {
        return [500, ['error' => 'Error de conexión con el servidor central: ' . $err]];
    }
    
    $decoded = json_decode($resp, true);
    return [$code, $decoded ?: ['raw_response' => $resp]];
}

if ($action === 'catalogo') {
    [$code, $data] = muhu_call('GET', '/ingest-catalogo.php');
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    
    // 1. Sanitizar nota (máximo 300 caracteres, sin HTML)
    $notaRaw = $in['nota'] ?? '';
    $nota = trim(strip_tags($notaRaw));
    if (mb_strlen($nota) > 300) {
        $nota = mb_substr($nota, 0, 300);
    }
    
    // 2. Validar y sanitizar request_id (UUID único generado por el JS cliente)
    $requestIdRaw = $in['request_id'] ?? '';
    if (!preg_match('/^[a-f0-9\-]{36}$/i', $requestIdRaw)) {
        // Fallback robusto a un token aleatorio seguro si viene malformado o ausente
        $requestId = bin2hex(random_bytes(16));
    } else {
        $requestId = $requestIdRaw;
    }
    
    // 3. Sanitizar y validar items
    $itemsRaw = $in['items'] ?? [];
    $items = [];
    
    if (!is_array($itemsRaw)) {
        http_response_code(400);
        echo json_encode(['error' => 'El listado de ítems debe ser un arreglo válido.']);
        exit;
    }
    
    foreach ($itemsRaw as $item) {
        $codigo = trim(strip_tags($item['codigo'] ?? ''));
        $cantidad = filter_var($item['cantidad'] ?? 0, FILTER_VALIDATE_FLOAT);
        
        // El catálogo exige código y cantidad estrictamente positiva
        if (!empty($codigo) && $cantidad !== false && $cantidad > 0) {
            $items[] = [
                'codigo'   => $codigo,
                'cantidad' => round($cantidad, 2)
            ];
        }
    }
    
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'El pedido debe incluir al menos un ítem con cantidad mayor a 0.']);
        exit;
    }
    
    $payload = [
        'autor'      => $_SESSION['user']['name'] ?? 'App Pedidos',
        'nota'       => $nota,
        'request_id' => $requestId,
        'items'      => $items,
    ];
    
    [$code, $data] = muhu_call('POST', '/ingest-pedido.php', $payload);
    http_response_code($code);
    echo json_encode($data);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida o método incorrecto.']);
