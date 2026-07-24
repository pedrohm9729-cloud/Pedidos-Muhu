<?php
require_once __DIR__ . '/config.php';

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS'])),
    'cookie_samesite' => 'Strict',
]);

header('Content-Type: application/json; charset=UTF-8');

// Exigir sesión activa (usuario logueado en ESTA app)
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

$username = $_SESSION['user']['username'] ?? '';
$role = $_SESSION['user']['role'] ?? ($username === 'admin' ? 'admin' : 'staff');
$action = $_GET['action'] ?? '';

// ── Almacenamiento local de pedidos ──────────────────────────────
// El servidor central sólo expone ingesta (no listado), así que esta app
// guarda una copia local de cada pedido para el panel de gestión del admin.
define('DATA_DIR', __DIR__ . '/data');
define('PEDIDOS_FILE', DATA_DIR . '/pedidos.json');

const ESTADOS_VALIDOS = ['enviado', 'preparacion', 'completado', 'anulado', 'error'];

function ensure_data_dir(): void {
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0700, true);
    }
    // Defensa en profundidad: negar acceso web al directorio de datos.
    $ht = DATA_DIR . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
}

function pedidos_load(): array {
    if (!file_exists(PEDIDOS_FILE)) return [];
    $raw = @file_get_contents(PEDIDOS_FILE);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function pedidos_save(array $all): bool {
    ensure_data_dir();
    $tmp = PEDIDOS_FILE . '.tmp';
    $ok = @file_put_contents($tmp, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok === false) return false;
    return @rename($tmp, PEDIDOS_FILE);
}

// Upsert por request_id (clave de idempotencia).
function pedidos_upsert(array $record): void {
    ensure_data_dir();
    $fp = @fopen(PEDIDOS_FILE, 'c+');
    if ($fp === false) return;
    @flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $all = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?: []) : [];

    $found = false;
    foreach ($all as $i => $p) {
        if (($p['request_id'] ?? null) === $record['request_id']) {
            // Conservar estado ya gestionado por el admin si existía.
            $record['estado'] = $p['estado'] ?? $record['estado'];
            $record['creado_en'] = $p['creado_en'] ?? $record['creado_en'];
            $all[$i] = $record;
            $found = true;
            break;
        }
    }
    if (!$found) {
        array_unshift($all, $record); // más recientes primero
    }
    // Limitar histórico local a 500 registros.
    if (count($all) > 500) $all = array_slice($all, 0, 500);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
}

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

// Verifica que el Origin/Referer coincida con el host (mitiga CSRF).
function guard_csrf(): void {
    $allowed_host = $_SERVER['HTTP_HOST'] ?? '';
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $parts = parse_url($_SERVER['HTTP_ORIGIN']);
        if (($parts['host'] ?? '') !== $allowed_host) {
            http_response_code(403);
            echo json_encode(['error' => 'Origen no permitido (CSRF bloqueado).']);
            exit;
        }
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $parts = parse_url($_SERVER['HTTP_REFERER']);
        if (($parts['host'] ?? '') !== $allowed_host) {
            http_response_code(403);
            echo json_encode(['error' => 'Referencia no permitida (CSRF bloqueado).']);
            exit;
        }
    }
}

// ── Catálogo (todos los usuarios logueados) ──────────────────────
if ($action === 'catalogo') {
    [$code, $data] = muhu_call('GET', '/ingest-catalogo.php');
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── Enviar pedido (personal / staff) ─────────────────────────────
if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    guard_csrf();

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
        $requestId = bin2hex(random_bytes(16));
    } else {
        $requestId = $requestIdRaw;
    }

    // 3. Sanitizar y validar items
    $itemsRaw = $in['items'] ?? [];
    if (!is_array($itemsRaw)) {
        http_response_code(400);
        echo json_encode(['error' => 'El listado de ítems debe ser un arreglo válido.']);
        exit;
    }

    $items = [];
    foreach ($itemsRaw as $item) {
        $codigo = trim(strip_tags($item['codigo'] ?? ''));
        $cantidad = filter_var($item['cantidad'] ?? 0, FILTER_VALIDATE_FLOAT);
        if (!empty($codigo) && preg_match('/^[A-Z0-9\-]+$/i', $codigo) && $cantidad !== false && $cantidad > 0) {
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

    $autor = $_SESSION['user']['name'] ?? 'App Pedidos';
    $payload = [
        'autor'      => $autor,
        'nota'       => $nota,
        'request_id' => $requestId,
        'items'      => $items,
    ];

    [$code, $data] = muhu_call('POST', '/ingest-pedido.php', $payload);
    $ok = ($code >= 200 && $code < 300 && !empty($data['success']));

    // Guardar copia local para el panel de gestión (independiente del central).
    $totalUnidades = 0.0;
    foreach ($items as $it) { $totalUnidades += (float)$it['cantidad']; }
    pedidos_upsert([
        'request_id'     => $requestId,
        'folio'          => $data['folio'] ?? null,
        'autor'          => $autor,
        'usuario'        => $username,
        'nota'           => $nota,
        'items'          => $items,
        'total_lineas'   => count($items),
        'total_unidades' => round($totalUnidades, 2),
        'estado'         => $ok ? 'enviado' : 'error',
        'creado_en'      => date('c'),
        'actualizado_en' => date('c'),
    ]);

    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── Listar pedidos (sólo admin) ──────────────────────────────────
if ($action === 'pedidos') {
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso restringido al administrador.']);
        exit;
    }
    echo json_encode(['pedidos' => pedidos_load()]);
    exit;
}

// ── Actualizar estado de un pedido (sólo admin) ──────────────────
if ($action === 'estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso restringido al administrador.']);
        exit;
    }
    guard_csrf();

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = trim(strip_tags($in['request_id'] ?? ''));
    $estado = $in['estado'] ?? '';

    if ($id === '' || !in_array($estado, ESTADOS_VALIDOS, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos para actualizar el estado.']);
        exit;
    }

    $all = pedidos_load();
    $found = false;
    foreach ($all as $i => $p) {
        if (($p['request_id'] ?? null) === $id) {
            $all[$i]['estado'] = $estado;
            $all[$i]['actualizado_en'] = date('c');
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido no encontrado.']);
        exit;
    }

    if (!pedidos_save($all)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo guardar el estado.']);
        exit;
    }

    echo json_encode(['success' => true, 'estado' => $estado]);
    exit;
}

// ── Mis pedidos (personal: solo los propios) ──────────────────────
if ($action === 'mis-pedidos') {
    $all = pedidos_load();
    $mios = array_values(array_filter($all, fn($p) => ($p['usuario'] ?? '') === $username));
    echo json_encode(['pedidos' => $mios]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida o método incorrecto.']);
