<?php
/**
 * catalogo-admin.php — Gestión del catálogo de insumos
 * Accesible solo para administradores de pedidos.muhucafeteria.com
 */
require_once __DIR__ . '/config.php';
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS'])),
    'cookie_samesite' => 'Strict',
]);

if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
    session_unset(); session_destroy();
    header('Location: login.php?error=Sesión expirada.'); exit;
}
$_SESSION['last_activity'] = time();

$role    = $_SESSION['user']['role'] ?? (($_SESSION['user']['username'] ?? '') === 'admin' ? 'admin' : 'staff');
$isAdmin = ($role === 'admin');
if (!$isAdmin) { header('Location: index.php'); exit; }

$userName    = $_SESSION['user']['name'] ?? 'Admin';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

// Ruta absoluta a la BD de ops (mismo servidor, mismo usuario)
$OPS_DB = '/home/u746393752/domains/muhucafeteria.com/public_html/ops/data/muhu-ops.sqlite';

$msg = ''; $err = '';

try {
    $db = new PDO('sqlite:' . $OPS_DB, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 3000;');
} catch (\Exception $e) {
    die('<p style="color:red;font-family:sans-serif;padding:40px">No se pudo conectar a la base de datos de catálogo: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

function obtenerPrefijoCategoria(PDO $db, string $categoria): string {
    $st = $db->prepare('SELECT codigo FROM catalogo WHERE LOWER(categoria) = LOWER(?) AND codigo LIKE "%-%" LIMIT 50');
    $st->execute([$categoria]);
    $codigos = $st->fetchAll(PDO::FETCH_COLUMN);

    foreach ($codigos as $c) {
        if (preg_match('/^([A-Z0-9]+)\-/i', $c, $m)) {
            return strtoupper($m[1]);
        }
    }

    $cleanCat = preg_replace('/[^A-Za-z0-9]/', '', mb_strtoupper($categoria));
    $prefix = mb_substr($cleanCat, 0, 2);
    if (strlen($prefix) < 2) {
        $prefix = str_pad($prefix, 2, 'X');
    }

    return $prefix;
}

function generarCorrelativo(PDO $db, string $categoria): string {
    $prefix = obtenerPrefijoCategoria($db, $categoria);
    
    $st = $db->prepare('SELECT codigo FROM catalogo WHERE codigo LIKE ?');
    $st->execute([$prefix . '-%']);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $maxNum = 0;
    foreach ($rows as $c) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '\-(\d+)$/i', $c, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }

    $nextNum = $maxNum + 1;
    return sprintf('%s-%03d', $prefix, $nextNum);
}

$old = [];
// ── POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'generar_codigo') {
        header('Content-Type: application/json; charset=UTF-8');
        $cat = trim($_POST['categoria'] ?? '');
        $code = $cat ? generarCorrelativo($db, $cat) : '';
        echo json_encode(['codigo' => $code]);
        exit;
    }

    if ($accion === 'agregar') {
        $codigo    = strtoupper(trim($_POST['codigo']    ?? ''));
        $nombre    = trim($_POST['nombre']    ?? '');
        $unidad    = trim($_POST['unidad']    ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $proveedor = trim($_POST['proveedor'] ?? '') ?: 'Otro';

        // Si el código está vacío, generar correlativo automático por categoría
        if (!$codigo && $categoria) {
            $codigo = generarCorrelativo($db, $categoria);
        }
        
        $old = [
            'codigo'    => $_POST['codigo'] ?? '',
            'nombre'    => $nombre,
            'unidad'    => $unidad,
            'categoria' => $categoria,
            'proveedor' => $_POST['proveedor'] ?? '',
        ];

        if (!$nombre || !$unidad || !$categoria) {
            $err = 'Nombre, unidad y categoría son obligatorios.';
        } elseif (!$codigo) {
            $err = 'No se pudo generar un código correlativo. Especifica uno manualmente.';
        } elseif (!preg_match('/^[A-Z0-9\-]+$/', $codigo)) {
            $err = 'El código solo puede tener letras, números y guiones (ej. VE-007).';
        } else {
            try {
                $db->prepare('INSERT INTO catalogo (codigo,nombre,unidad,categoria,proveedor,activo) VALUES (?,?,?,?,?,1)')
                   ->execute([$codigo, $nombre, $unidad, $categoria, $proveedor]);
                $msg = "Ítem «{$nombre}» agregado con éxito con el código «{$codigo}».";
                $old = []; // Limpiar formulario al agregar exitosamente
            } catch (\Exception $e) {
                $err = "El código «{$codigo}» ya existe. Usa uno diferente.";
            }
        }
    }

    if ($accion === 'editar') {
        $codigo    = trim($_POST['codigo']    ?? '');
        $nombre    = trim($_POST['nombre']    ?? '');
        $unidad    = trim($_POST['unidad']    ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $proveedor = trim($_POST['proveedor'] ?? '') ?: 'Otro';
        if ($codigo && $nombre && $unidad && $categoria) {
            $db->prepare('UPDATE catalogo SET nombre=?,unidad=?,categoria=?,proveedor=? WHERE codigo=?')
               ->execute([$nombre, $unidad, $categoria, $proveedor, $codigo]);
            $msg = "Ítem «{$nombre}» actualizado.";
        }
    }

    if ($accion === 'toggle') {
        $codigo = trim($_POST['codigo'] ?? '');
        if ($codigo) {
            $st = $db->prepare('SELECT activo FROM catalogo WHERE codigo=?');
            $st->execute([$codigo]);
            $actual = (int)$st->fetchColumn();
            $nuevo  = $actual ? 0 : 1;
            $db->prepare('UPDATE catalogo SET activo=? WHERE codigo=?')->execute([$nuevo, $codigo]);
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => true, 'nuevo_activo' => $nuevo]);
                exit;
            }
            $msg = $nuevo ? 'Ítem activado.' : 'Ítem desactivado (no aparecerá en pedidos).';
        }
    }

    if ($accion === 'eliminar') {
        $codigo = trim($_POST['codigo'] ?? '');
        if ($codigo) {
            $db->prepare('DELETE FROM catalogo WHERE codigo=?')->execute([$codigo]);
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => true, 'codigo' => $codigo]);
                exit;
            }
            $msg = 'Ítem eliminado permanentemente.';
        }
    }

    // ── Guardar URL de Google Sheets ────────────────────────────
    if ($accion === 'guardar_url') {
        $url = trim($_POST['sheets_url'] ?? '');
        $cfg = ['sheets_url' => $url];
        file_put_contents(__DIR__ . '/data/catalogo-config.json', json_encode($cfg));
        $msg = $url ? 'URL de Google Sheets guardada.' : 'URL eliminada.';
    }

    // ── Sincronizar desde Google Sheets ────────────────────────
    if ($accion === 'sync_sheets') {
        $cfg_file = __DIR__ . '/data/catalogo-config.json';
        $cfg_data = is_file($cfg_file) ? json_decode(file_get_contents($cfg_file), true) : [];
        $sheets_url = trim($cfg_data['sheets_url'] ?? ($_POST['sheets_url'] ?? ''));

        // Convertir URL de Google Sheets a CSV export
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheets_url, $m)) {
            $sheet_id  = $m[1];
            // Detectar gid si viene en la URL
            preg_match('#[#&?]gid=([0-9]+)#', $sheets_url, $mg);
            $gid = $mg[1] ?? '0';
            $csv_url = "https://docs.google.com/spreadsheets/d/{$sheet_id}/export?format=csv&gid={$gid}";
        } else {
            $csv_url = $sheets_url; // Asumir que ya es CSV directo
        }

        $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => true,
            'header' => "User-Agent: MUHU-Pedidos/1.0\r\n"]]);
        $csv_raw = @file_get_contents($csv_url, false, $ctx);
        if ($csv_raw === false) {
            $err = 'No se pudo descargar la hoja. Verifica que esté publicada como «Cualquiera con el enlace puede ver».';
        } else {
            [$ok, $err, $msg] = importar_csv_string($db, $csv_raw);
        }
    }

    // ── Importar CSV/Excel subido ───────────────────────────────
    if ($accion === 'upload_csv' && isset($_FILES['archivo'])) {
        $file = $_FILES['archivo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $err = 'Error al subir el archivo (código ' . $file['error'] . ').';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'])) {
                $err = 'Solo se aceptan archivos .csv. Exporta tu Excel como CSV primero (Archivo → Guardar como → CSV).';
            } else {
                $csv_raw = file_get_contents($file['tmp_name']);
                // Detectar y convertir encoding
                $enc = mb_detect_encoding($csv_raw, ['UTF-8','ISO-8859-1','Windows-1252'], true);
                if ($enc && $enc !== 'UTF-8') $csv_raw = mb_convert_encoding($csv_raw, 'UTF-8', $enc);
                [$ok, $err, $msg] = importar_csv_string($db, $csv_raw);
            }
        }
    }
}

/**
 * Parsea un string CSV y hace upsert en la tabla catalogo.
 * Columnas esperadas (en cualquier orden): codigo, nombre, unidad, categoria, proveedor
 * @return array [bool $ok, string $err, string $msg]
 */
function importar_csv_string(PDO $db, string $csv): array {
    $csv = ltrim($csv, "\xEF\xBB\xBF");
    $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $csv)));
    if (count($lines) < 2) return [false, 'El archivo está vacío o tiene menos de 2 filas.', ''];

    $first = $lines[0];
    $sep   = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';

    $header = array_map(fn($h) => mb_strtolower(trim($h, " \t\n\r\0\x0B\"'")), str_getcsv($first, $sep));

    $map = [];
    foreach ($header as $i => $h) {
        if (str_contains($h, 'cod') || str_contains($h, 'item') || str_contains($h, 'ítem')) $map['codigo'] = $i;
        if (str_contains($h, 'nom') || str_contains($h, 'desc'))                              $map['nombre'] = $i;
        if (str_contains($h, 'uni') || str_contains($h, 'u.m'))                               $map['unidad'] = $i;
        if (str_contains($h, 'cat'))                                                          $map['categoria'] = $i;
        if (str_contains($h, 'prov'))                                                         $map['proveedor'] = $i;
    }

    $req = ['nombre', 'unidad', 'categoria'];
    foreach ($req as $r) {
        if (!isset($map[$r])) return [false, "No se encontró la columna «{$r}» en la hoja. Encabezados detectados: " . implode(', ', $header), ''];
    }

    $CAT_PREFIX = [
        'abarrotes' => 'AB', 'cafe' => 'CA', 'carnicos' => 'CR', 'frutas' => 'FR',
        'huevos' => 'HU', 'descartables' => 'DE', 'gas/limpieza' => 'GL', 'lacteos' => 'LA',
        'verduras' => 'VE', 'aves' => 'AV',
    ];

    $db->exec('BEGIN TRANSACTION; DELETE FROM catalogo;');
    $ins = $db->prepare('INSERT OR REPLACE INTO catalogo (codigo,nombre,unidad,categoria,proveedor,activo) VALUES (?,?,?,?,?,1)');
    $ok_count = 0; $skip = 0;
    $cat_counters = [];
    array_shift($lines); // quitar encabezado

    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $cols = str_getcsv($line, $sep);
        $nombre    = trim($cols[$map['nombre']] ?? '');
        $unidad    = trim($cols[$map['unidad']] ?? 'Und') ?: 'Und';
        $categoria = mb_convert_case(trim($cols[$map['categoria']] ?? 'Abarrotes'), MB_CASE_TITLE, 'UTF-8');
        $proveedor = trim($cols[$map['proveedor']] ?? 'Otro') ?: 'Otro';

        if (!$nombre) { $skip++; continue; }

        if (isset($map['codigo']) && !empty(trim($cols[$map['codigo']] ?? '')) && preg_match('/^[A-Z]{2,4}\-\d+$/i', trim($cols[$map['codigo']]))) {
            $codigo = strtoupper(trim($cols[$map['codigo']]));
        } else {
            $cat_raw = mb_strtolower($categoria);
            $prefix = $CAT_PREFIX[$cat_raw] ?? 'OT';
            $cat_counters[$prefix] = ($cat_counters[$prefix] ?? 0) + 1;
            $codigo = $prefix . '-' . str_pad((string)$cat_counters[$prefix], 3, '0', STR_PAD_LEFT);
        }

        $ins->execute([$codigo, $nombre, $unidad, $categoria, $proveedor]);
        $ok_count++;
    }
    $db->exec('COMMIT;');
    return [true, '', "✅ {$ok_count} ítems importados correctamente con sus proveedores."];
}

// ── GET ─────────────────────────────────────────────────────────
$items = $db->query('SELECT * FROM catalogo ORDER BY categoria, nombre')->fetchAll();
$cats  = array_unique(array_column($items, 'categoria'));
sort($cats);

$editar = null;
if (isset($_GET['editar'])) {
    $st = $db->prepare('SELECT * FROM catalogo WHERE codigo=?');
    $st->execute([$_GET['editar']]);
    $editar = $st->fetch() ?: null;
}

// Cargar URL guardada de Google Sheets
$cfg_file  = __DIR__ . '/data/catalogo-config.json';
$cfg_saved = is_file($cfg_file) ? json_decode(file_get_contents($cfg_file), true) : [];
$sheets_url_saved = $cfg_saved['sheets_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catálogo de Insumos — MUHU</title>
<link rel="icon" type="image/svg+xml" href="logo-gold.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#0c0a09;--bg-2:#100e0c;--surface:#1a1613;--surface-2:#221d18;--surface-3:#29231d;
    --line:#2e2a24;--line-2:#3d372f;--text:#f7f3ee;--muted:#b3aaa0;--muted-2:#857c72;
    --gold:#C9A052;--gold-h:#dcb56a;--gold-dim:rgba(201,160,82,.12);--gold-line:rgba(201,160,82,.38);
    --danger:#f0655a;--danger-dim:rgba(240,101,90,.12);
    --success:#34d399;--success-dim:rgba(52,211,153,.13);
    --warn:#fbbf24;--warn-dim:rgba(251,191,36,.13);
    --radius:16px;--radius-sm:11px;--sidebar-w:264px;--font:'Outfit',-apple-system,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;}

/* Sidebar */
.sidebar{width:var(--sidebar-w);background:var(--bg-2);border-right:1px solid var(--line);
    display:flex;flex-direction:column;padding:24px 16px;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;}
.brand{display:flex;align-items:center;gap:12px;padding:0 6px;margin-bottom:32px;}
.brand-mark{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--gold),#a07030);
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;color:#1a1200;flex-shrink:0;}
.brand h1{font-size:1.1rem;font-weight:700;color:var(--text);}
.brand p{font-size:0.72rem;color:var(--muted);}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface);
    border:1px solid var(--line);border-radius:12px;margin-bottom:24px;}
.user-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold-dim);border:1.5px solid var(--gold-line);
    display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--gold);font-size:.95rem;flex-shrink:0;}
.name{font-size:.85rem;font-weight:600;}
.role-badge{font-size:.68rem;font-weight:600;padding:2px 8px;border-radius:20px;letter-spacing:.04em;text-transform:uppercase;
    background:var(--gold-dim);color:var(--gold);border:1px solid var(--gold-line);}
.nav-label{font-size:.68rem;font-weight:700;color:var(--muted-2);letter-spacing:.08em;text-transform:uppercase;padding:0 6px;margin-bottom:6px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;cursor:pointer;
    font-size:.88rem;font-weight:500;color:var(--muted);transition:all .18s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:var(--surface);color:var(--text);}
.nav-item.active{background:var(--gold-dim);color:var(--gold);border:1px solid var(--gold-line);}
.nav-item svg{width:18px;height:18px;flex-shrink:0;}
.sidebar-spacer{flex:1;}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;background:none;border:1px solid var(--line);
    border-radius:11px;color:var(--muted);font-family:var(--font);font-size:.85rem;font-weight:500;
    padding:10px 14px;cursor:pointer;transition:all .18s;}
.btn-logout:hover{border-color:var(--danger);color:var(--danger);}
.btn-logout svg{width:17px;height:17px;}

/* Main */
.main{margin-left:var(--sidebar-w);flex:1;padding:32px 36px;max-width:1200px;}
.page-head{margin-bottom:28px;}
.page-head h2{font-size:1.45rem;font-weight:700;margin-bottom:4px;}
.page-head p{color:var(--muted);font-size:.9rem;}

/* Alert */
.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.88rem;display:flex;align-items:center;gap:10px;}
.alert.ok {background:var(--success-dim);border:1px solid var(--success);color:var(--success);}
.alert.err{background:var(--danger-dim); border:1px solid var(--danger); color:var(--danger);}

/* Form card */
.form-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:24px;margin-bottom:28px;}
.form-card h3{font-size:.95rem;font-weight:600;color:var(--gold);margin-bottom:18px;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px;align-items:end;}
.field{display:flex;flex-direction:column;justify-content:flex-end;}
.field label{font-size:.73rem;color:var(--muted);display:block;margin-bottom:5px;font-weight:600;
    text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;height:18px;line-height:18px;}
input[type=text],input[type=text]:focus,select{width:100%;background:var(--surface-2);
    border:1px solid var(--line);border-radius:10px;color:var(--text);font-family:var(--font);
    font-size:.88rem;padding:10px 13px;outline:none;transition:border .2s;}
input[type=text]:focus,select:focus{border-color:var(--gold);}
.btn{display:inline-flex;align-items:center;gap:6px;border:none;border-radius:10px;cursor:pointer;
    font-family:var(--font);font-size:.85rem;font-weight:600;padding:10px 20px;transition:all .18s;}
.btn-gold{background:var(--gold);color:#1a1200;}
.btn-gold:hover{background:var(--gold-h);}
.btn-ghost{background:var(--surface-2);color:var(--text);border:1px solid var(--line);}
.btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.btn-danger{background:var(--danger-dim);color:var(--danger);border:1px solid var(--danger);}
.btn-danger:hover{background:var(--danger);color:#fff;}
.btn-sm{padding:6px 12px;font-size:.76rem;border-radius:8px;}
.form-actions{display:flex;gap:10px;flex-wrap:wrap;}

/* Table */
.tbl-wrap{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;}
.tbl-top{padding:16px 20px;border-bottom:1px solid var(--line);display:flex;align-items:center;
    justify-content:space-between;flex-wrap:wrap;gap:10px;}
.tbl-top h3{font-size:.95rem;font-weight:600;}
.count{font-size:.8rem;color:var(--muted);margin-left:8px;}
.search-wrap{position:relative;}
.search-wrap svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);width:16px;height:16px;}
.search-inp{background:var(--surface-2);border:1px solid var(--line);border-radius:10px;
    color:var(--text);font-family:var(--font);font-size:.85rem;padding:8px 12px 8px 34px;
    outline:none;width:220px;}
.search-inp:focus{border-color:var(--gold);}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--surface-2);padding:10px 16px;text-align:left;font-size:.72rem;
    color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;
    border-bottom:1px solid var(--line);}
tbody tr{border-bottom:1px solid var(--line);transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:rgba(201,160,82,.04);}
td{padding:11px 16px;font-size:.86rem;vertical-align:middle;}
.code{font-family:monospace;font-size:.8rem;color:var(--gold);background:var(--gold-dim);
    padding:3px 8px;border-radius:6px;}
.cat-badge{font-size:.73rem;background:var(--surface-2);border:1px solid var(--line);
    border-radius:20px;padding:2px 10px;color:var(--muted);}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
.dot.on {background:var(--success);box-shadow:0 0 5px var(--success);}
.dot.off{background:var(--muted-2);}
.row-off{opacity:.45;}
.acts{display:flex;gap:6px;align-items:center;}
.empty-state{padding:56px;text-align:center;color:var(--muted);}
</style>
</head>
<body>
<!-- ─── Sidebar ─── -->
<aside class="sidebar">
    <div class="brand" style="padding:16px 12px 20px 12px;margin-bottom:12px;border-bottom:1px solid var(--line);">
        <img src="logo-gold.svg" alt="MUHU Cafetería" style="width:100%;max-width:180px;height:auto;display:block;" title="MUHU Cafetería">
    </div>
    <div class="user-chip">
        <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
        <div><div class="name"><?= htmlspecialchars($userName) ?></div>
            <span class="role-badge">Administrador</span></div>
    </div>
    <nav>
        <div class="nav-label">Gestión</div>
        <a href="index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span>Pedidos en curso</span>
        </a>
        <a href="catalogo-admin.php" class="nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 3H8a2 2 0 0 0-2 2v2h12V5a2 2 0 0 0-2-2z"/></svg>
            <span>Catálogo de insumos</span>
        </a>
    </nav>
    <div class="sidebar-spacer"></div>
    <form action="logout.php" method="POST">
        <button type="submit" class="btn-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- ─── Main ─── -->
<main class="main">
    <div class="page-head">
        <h2>📦 Catálogo de Insumos</h2>
        <p>Agrega, edita o desactiva los productos que aparecen en la app de pedidos del personal.</p>
    </div>

    <?php if ($msg): ?><div class="alert ok">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Formulario -->
    <div class="form-card">
        <h3><?= $editar ? '✏️ Editar ítem' : '➕ Agregar nuevo ítem' ?></h3>
        <form method="POST">
            <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'agregar' ?>">
            <div class="form-grid">
                <div class="field">
                    <label>Código <span style="text-transform:none;font-weight:400;color:var(--gold-soft);font-size:0.7rem;">(Auto)</span></label>
                    <input type="text" name="codigo" id="codigoInput" value="<?= htmlspecialchars($editar['codigo'] ?? $old['codigo'] ?? '') ?>"
                        placeholder="ej. VE-007 (o dejar en blanco)" maxlength="20"
                        <?= $editar ? 'readonly style="opacity:.55;cursor:not-allowed"' : '' ?>>
                </div>
                <div class="field">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($editar['nombre'] ?? $old['nombre'] ?? '') ?>"
                        placeholder="ej. Zapallo macre" maxlength="80" required>
                </div>
                <div class="field">
                    <label>Unidad *</label>
                    <input type="text" name="unidad" list="unit-list" value="<?= htmlspecialchars($editar['unidad'] ?? $old['unidad'] ?? '') ?>"
                        placeholder="ej. kg, und, paquete, caja" maxlength="30" required>
                    <datalist id="unit-list">
                        <option value="und">Unidad (und)</option>
                        <option value="kg">Kilo (kg)</option>
                        <option value="gr">Gramo (gr)</option>
                        <option value="l">Litro (l)</option>
                        <option value="ml">Mililitro (ml)</option>
                        <option value="paquete">Paquete</option>
                        <option value="caja">Caja</option>
                        <option value="bolsa">Bolsa</option>
                        <option value="millar">Millar</option>
                        <option value="ciento">Ciento</option>
                        <option value="docena">Docena</option>
                        <option value="lata">Lata</option>
                        <option value="atado">Atado</option>
                        <option value="botella">Botella</option>
                        <option value="frasco">Frasco</option>
                        <option value="balde">Balde</option>
                        <option value="rollo">Rollo</option>
                    </datalist>
                </div>
                <div class="field">
                    <label>Categoría *</label>
                    <input type="text" name="categoria" list="cat-list"
                        value="<?= htmlspecialchars($editar['categoria'] ?? $old['categoria'] ?? '') ?>"
                        placeholder="ej. Verduras" maxlength="40" required>
                    <datalist id="cat-list">
                        <?php foreach ($cats as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="field">
                    <label>Proveedor</label>
                    <input type="text" name="proveedor" list="prov-list" value="<?= htmlspecialchars($editar['proveedor'] ?? $old['proveedor'] ?? '') ?>"
                        placeholder="ej. Mercado Mayorista o seleccionar de lista" maxlength="60">
                    <datalist id="prov-list">
                        <?php 
                        $provList = array_unique(array_merge($provs ?? [], ['Makro', 'Tony Grafh', 'Alliexpress', 'Biopacking', 'Mercado Mayorista', 'Gloria', 'Alicorp', 'Avícola', 'Otro']));
                        sort($provList);
                        foreach ($provList as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-gold">
                    <?= $editar ? '💾 Guardar cambios' : '➕ Agregar ítem' ?>
                </button>
                <?php if ($editar): ?>
                <a href="catalogo-admin.php" class="btn btn-ghost">✕ Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── Panel de importación ── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px;">

        <!-- Google Sheets -->
        <div class="form-card" style="margin-bottom:0;">
            <h3>🔗 Sincronizar desde Google Sheets</h3>
            <p style="color:var(--muted);font-size:.83rem;margin-bottom:14px;">
                Pega el link de tu Google Sheet. La hoja debe tener columnas:
                <code style="background:var(--surface-2);padding:2px 6px;border-radius:5px;font-size:.8rem;">codigo, nombre, unidad, categoria, proveedor</code>
                y estar compartida como "Cualquiera con el enlace puede ver".
            </p>
            <form method="POST" style="display:flex;flex-direction:column;gap:10px;">
                <input type="hidden" name="accion" value="guardar_url">
                <div class="field">
                    <label>URL de Google Sheets</label>
                    <input type="text" name="sheets_url"
                        value="<?= htmlspecialchars($sheets_url_saved) ?>"
                        placeholder="https://docs.google.com/spreadsheets/d/..." style="font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-ghost" style="align-self:flex-start;">💾 Guardar URL</button>
            </form>
            <?php if ($sheets_url_saved): ?>
            <form method="POST" style="margin-top:12px;">
                <input type="hidden" name="accion" value="sync_sheets">
                <button type="submit" class="btn btn-gold">🔄 Sincronizar ahora</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Subir CSV -->
        <div class="form-card" style="margin-bottom:0;">
            <h3>📤 Subir archivo CSV</h3>
            <p style="color:var(--muted);font-size:.83rem;margin-bottom:14px;">
                Exporta tu Excel como <strong>CSV</strong> (Archivo → Guardar como → CSV UTF-8).
                El archivo debe tener encabezados:
                <code style="background:var(--surface-2);padding:2px 6px;border-radius:5px;font-size:.8rem;">codigo, nombre, unidad, categoria, proveedor</code>
            </p>
            <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                <input type="hidden" name="accion" value="upload_csv">
                <div class="field">
                    <label>Archivo CSV</label>
                    <input type="file" name="archivo" accept=".csv,.txt"
                        style="background:var(--surface-2);border:1px solid var(--line);border-radius:10px;padding:10px;font-size:.85rem;color:var(--text);">
                </div>
                <button type="submit" class="btn btn-gold" style="align-self:flex-start;">📥 Importar CSV</button>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="tbl-wrap">
        <div class="tbl-top">
            <div>
                <h3 style="display:inline">Ítems en catálogo</h3>
                <span class="count"><?= count($items) ?> total · <?= count(array_filter($items, fn($i) => $i['activo'])) ?> activos</span>
            </div>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="search-inp" id="srch" placeholder="Buscar ítem..." oninput="filtrar(this.value)">
            </div>
        </div>
        <?php if (!$items): ?>
        <div class="empty-state">No hay ítems en el catálogo aún. ¡Agrega el primero!</div>
        <?php else: ?>
        <table id="tbl">
            <thead>
                <tr>
                    <th style="width:40px">Estado</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Unidad</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th style="width:230px">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
            <tr class="<?= $it['activo'] ? '' : 'row-off' ?>"
                data-q="<?= mb_strtolower(htmlspecialchars($it['nombre'].' '.$it['codigo'].' '.$it['categoria'])) ?>">
                <td><span class="dot <?= $it['activo'] ? 'on' : 'off' ?>" title="<?= $it['activo'] ? 'Activo' : 'Inactivo' ?>"></span></td>
                <td><span class="code"><?= htmlspecialchars($it['codigo']) ?></span></td>
                <td><?= htmlspecialchars($it['nombre']) ?></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($it['unidad']) ?></td>
                <td><span class="cat-badge"><?= htmlspecialchars($it['categoria']) ?></span></td>
                <td style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars($it['proveedor']) ?></td>
                <td>
                    <div class="acts">
                        <a href="?editar=<?= urlencode($it['codigo']) ?>" class="btn btn-ghost btn-sm">✏️ Editar</a>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleItemAjax('<?= htmlspecialchars($it['codigo']) ?>', this)"><?= $it['activo'] ? '🚫' : '✅' ?></button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminarItemAjax('<?= htmlspecialchars($it['codigo']) ?>', '<?= addslashes(htmlspecialchars($it['nombre'])) ?>', this)">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<script>
function filtrar(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#tbl tbody tr').forEach(tr => {
        tr.style.display = tr.dataset.q.includes(q) ? '' : 'none';
    });
}

window.eliminarItemAjax = async function(codigo, nombre, btnEl) {
    if (!confirm(`¿Eliminar «${nombre}» para siempre?`)) return;

    btnEl.disabled = true;
    try {
        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('codigo', codigo);
        fd.append('ajax', '1');

        const r = await fetch('catalogo-admin.php', { method: 'POST', body: fd });
        const data = await r.json();

        if (r.ok && data.success) {
            const tr = btnEl.closest('tr');
            if (tr) {
                tr.style.transition = 'opacity 0.25s, transform 0.25s';
                tr.style.opacity = '0';
                tr.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    tr.remove();
                    const searchInp = document.querySelector('.search-inp');
                    if (searchInp && searchInp.value) filtrar(searchInp.value);
                }, 250);
            }
        } else {
            alert('No se pudo eliminar el ítem.');
            btnEl.disabled = false;
        }
    } catch (e) {
        alert('Error al conectar con el servidor.');
        btnEl.disabled = false;
    }
};

window.toggleItemAjax = async function(codigo, btnEl) {
    btnEl.disabled = true;
    try {
        const fd = new FormData();
        fd.append('accion', 'toggle');
        fd.append('codigo', codigo);
        fd.append('ajax', '1');

        const r = await fetch('catalogo-admin.php', { method: 'POST', body: fd });
        const data = await r.json();

        if (r.ok && data.success) {
            const tr = btnEl.closest('tr');
            if (tr) {
                const dot = tr.querySelector('.dot');
                if (dot) {
                    if (data.nuevo_activo) {
                        dot.className = 'dot on';
                        dot.title = 'Activo';
                        btnEl.textContent = '🚫';
                        tr.classList.remove('row-off');
                    } else {
                        dot.className = 'dot off';
                        dot.title = 'Inactivo';
                        btnEl.textContent = '✅';
                        tr.classList.add('row-off');
                    }
                }
            }
        } else {
            alert('No se pudo cambiar el estado.');
        }
    } catch (e) {
        alert('Error al conectar con el servidor.');
    } finally {
        btnEl.disabled = false;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const searchInp = document.querySelector('.search-inp');
    if (searchInp) {
        const savedQ = sessionStorage.getItem('cat_search_q');
        if (savedQ) {
            searchInp.value = savedQ;
            filtrar(savedQ);
        }
        searchInp.addEventListener('input', e => {
            sessionStorage.setItem('cat_search_q', e.target.value);
            filtrar(e.target.value);
        });
    }

    const catInput = document.querySelector('input[name="categoria"]');
    const codeInput = document.getElementById('codigoInput');

    if (catInput && codeInput) {
        let timeout;
        const updateAutoCode = async () => {
            const catVal = catInput.value.trim();
            if (!catVal) return;
            if (codeInput.readOnly || (codeInput.dataset.auto === 'true' || !codeInput.value)) {
                try {
                    const fd = new FormData();
                    fd.append('accion', 'generar_codigo');
                    fd.append('categoria', catVal);
                    const r = await fetch('catalogo-admin.php', { method: 'POST', body: fd });
                    if (r.ok) {
                        const data = await r.json();
                        if (data.codigo && (!codeInput.value || codeInput.dataset.auto === 'true')) {
                            codeInput.value = data.codigo;
                            codeInput.dataset.auto = 'true';
                        }
                    }
                } catch (e) {}
            }
        };

        catInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(updateAutoCode, 300);
        });
        catInput.addEventListener('change', updateAutoCode);

        codeInput.addEventListener('input', () => {
            if (codeInput.value) {
                delete codeInput.dataset.auto;
            }
        });
    }
});
</script>
</body>
</html>
