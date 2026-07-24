<?php
require_once __DIR__ . '/config.php';
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS'])),
    'cookie_samesite' => 'Strict',
]);

// Redirigir al login si no hay sesión
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Validar timeout de inactividad de 15 minutos (900 segundos)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Sesión expirada por inactividad.');
    exit;
}
$_SESSION['last_activity'] = time();

$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
$role = $_SESSION['user']['role'] ?? (($_SESSION['user']['username'] ?? '') === 'admin' ? 'admin' : 'staff');
$isAdmin = ($role === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isAdmin ? 'Gestión de Pedidos' : 'Nuevo Pedido' ?> — MUHU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c0a09;
            --bg-2: #100e0c;
            --surface: #1a1613;
            --surface-2: #221d18;
            --surface-3: #29231d;
            --line: #2e2a24;
            --line-2: #3d372f;
            --text: #f7f3ee;
            --muted: #b3aaa0;
            --muted-2: #857c72;
            --gold: #C9A052;
            --gold-h: #dcb56a;
            --gold-soft: #e7c98f;
            --gold-dim: rgba(201, 160, 82, 0.12);
            --gold-line: rgba(201, 160, 82, 0.38);
            --danger: #f0655a;
            --danger-dim: rgba(240, 101, 90, 0.12);
            --success: #34d399;
            --success-dim: rgba(52, 211, 153, 0.13);
            --info: #60a5fa;
            --info-dim: rgba(96, 165, 250, 0.13);
            --warn: #fbbf24;
            --warn-dim: rgba(251, 191, 36, 0.13);
            --radius: 16px;
            --radius-sm: 11px;
            --sidebar-w: 264px;
            --font: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            --transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        html { font-size: 15px; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(900px 500px at 82% -8%, rgba(201, 160, 82, 0.09), transparent 60%),
                radial-gradient(700px 500px at -5% 105%, rgba(201, 160, 82, 0.05), transparent 55%);
            pointer-events: none;
            z-index: 0;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--line-2); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: #4a443a; }

        /* ── App shell ─────────────────────────────── */
        .app {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: var(--sidebar-w) 1fr;
            min-height: 100vh;
        }

        /* ── Sidebar ───────────────────────────────── */
        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            background: linear-gradient(180deg, var(--bg-2) 0%, #0a0807 100%);
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            padding: 22px 16px;
            gap: 22px;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding: 4px 8px; }
        .brand-mark {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, var(--gold-h), #a37c34);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 19px; color: #1a1206;
            box-shadow: 0 6px 18px rgba(201, 160, 82, 0.28);
            letter-spacing: -0.5px;
        }
        .brand-text h1 { font-size: 18px; font-weight: 800; letter-spacing: 0.5px; }
        .brand-text p { font-size: 11.5px; color: var(--muted-2); font-weight: 500; margin-top: 1px; }

        .user-chip {
            display: flex; align-items: center; gap: 11px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            padding: 11px 12px;
        }
        .user-avatar {
            width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
            background: var(--gold-dim); border: 1px solid var(--gold-line);
            color: var(--gold-soft); font-weight: 700; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .user-meta { overflow: hidden; }
        .user-meta .name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .role-badge {
            display: inline-block; margin-top: 3px;
            font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
            padding: 2px 8px; border-radius: 999px;
        }
        .role-badge.admin { background: var(--gold-dim); color: var(--gold-soft); border: 1px solid var(--gold-line); }
        .role-badge.staff { background: var(--info-dim); color: var(--info); border: 1px solid rgba(96,165,250,0.3); }

        .nav { display: flex; flex-direction: column; gap: 4px; }
        .nav-label { font-size: 11px; font-weight: 700; color: var(--muted-2); text-transform: uppercase; letter-spacing: 0.8px; padding: 0 10px 6px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 12px; border-radius: var(--radius-sm);
            color: var(--muted); font-size: 14.5px; font-weight: 600;
            cursor: pointer; transition: var(--transition); border: 1px solid transparent;
        }
        .nav-item svg { width: 19px; height: 19px; flex-shrink: 0; }
        .nav-item:hover { background: var(--surface); color: var(--text); }
        .nav-item.active {
            background: var(--gold-dim); color: var(--gold-soft);
            border-color: var(--gold-line);
        }

        .sidebar-spacer { flex: 1; }

        .btn-logout {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 12px; border-radius: var(--radius-sm);
            border: 1px solid var(--line-2); background: var(--surface);
            color: var(--muted); font-family: var(--font); font-size: 14px; font-weight: 600;
            cursor: pointer; transition: var(--transition);
        }
        .btn-logout:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-dim); }
        .btn-logout svg { width: 17px; height: 17px; }

        /* ── Main ──────────────────────────────────── */
        .main { min-width: 0; display: flex; flex-direction: column; }

        .topbar-mobile {
            display: none;
            align-items: center; gap: 12px;
            padding: 14px 16px;
            background: rgba(16, 14, 12, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
            position: sticky; top: 0; z-index: 80;
        }
        .hamburger {
            width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
            background: var(--surface); border: 1px solid var(--line);
            color: var(--text); display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .topbar-mobile .tm-title { font-weight: 700; font-size: 16px; }

        .page-head {
            padding: 26px 30px 16px;
            display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .page-head h2 { font-size: 24px; font-weight: 800; letter-spacing: -0.3px; }
        .page-head p { color: var(--muted); font-size: 14px; margin-top: 4px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: var(--radius-sm);
            font-family: var(--font); font-size: 14px; font-weight: 600; cursor: pointer;
            border: 1px solid var(--line-2); background: var(--surface); color: var(--text);
            transition: var(--transition);
        }
        .btn:hover { border-color: var(--gold-line); }
        .btn svg { width: 16px; height: 16px; }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold-h), #a87f33);
            border: none; color: #1a1206; font-weight: 700;
            box-shadow: 0 6px 16px rgba(201, 160, 82, 0.28);
        }
        .btn-gold:hover { filter: brightness(1.06); }
        .btn-gold:disabled { opacity: 0.55; cursor: not-allowed; filter: none; }

        /* ── Toolbar (search + chips) ──────────────── */
        .toolbar {
            position: sticky; top: 0; z-index: 60;
            background: rgba(12, 10, 9, 0.88);
            backdrop-filter: blur(10px);
            padding: 12px 30px 14px;
            border-bottom: 1px solid var(--line);
            display: flex; flex-direction: column; gap: 12px;
        }
        .search-wrap { position: relative; max-width: 440px; width: 100%; }
        .search-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted-2); width: 19px; height: 19px; pointer-events: none; }
        .search-input {
            width: 100%; height: 46px;
            background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--radius-sm); padding: 0 16px 0 44px;
            font-family: var(--font); font-size: 15px; color: var(--text);
            transition: var(--transition);
        }
        .search-input:focus { outline: none; border-color: var(--gold-line); box-shadow: 0 0 0 3px var(--gold-dim); }

        /* Chips: sin scrollbar visible, con wrap en escritorio */
        .chips {
            display: flex; gap: 8px; flex-wrap: wrap;
            scrollbar-width: none;
        }
        .chips::-webkit-scrollbar { display: none; }
        .chip {
            padding: 8px 15px; border-radius: 999px;
            background: var(--surface); border: 1px solid var(--line);
            color: var(--muted); font-size: 13.5px; font-weight: 600;
            white-space: nowrap; cursor: pointer; transition: var(--transition);
        }
        .chip:hover { color: var(--text); border-color: var(--line-2); }
        .chip.active {
            background: var(--gold-dim); border-color: var(--gold-line); color: var(--gold-soft);
        }

        /* ── Staff layout ──────────────────────────── */
        .staff-body {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            align-items: start;
        }
        .catalog-col { min-width: 0; }
        @media (min-width: 1080px) {
            .staff-body { grid-template-columns: 1fr minmax(330px, 372px); }
        }

        .catalog {
            padding: 20px 30px 40px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(258px, 1fr));
            gap: 15px;
            align-content: start;
        }

        .product-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 16px;
            display: flex; flex-direction: column; gap: 14px;
            transition: var(--transition);
        }
        .product-card:hover { border-color: var(--line-2); transform: translateY(-2px); }
        .product-card.has-items {
            border-color: var(--gold-line);
            box-shadow: 0 0 0 1px var(--gold-line), 0 10px 24px -12px rgba(201,160,82,0.4);
        }
        .pc-top { display: flex; flex-direction: column; gap: 6px; }
        .pc-tag {
            align-self: flex-start;
            font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--gold-soft); background: var(--gold-dim);
            padding: 3px 9px; border-radius: 6px; border: 1px solid var(--gold-line);
        }
        .pc-name { font-size: 16.5px; font-weight: 700; line-height: 1.25; }
        .pc-meta { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--muted-2); flex-wrap: wrap; }
        .pc-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--line-2); }
        .pc-bottom { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 2px; }
        .unit-badge {
            font-size: 12.5px; font-weight: 600; color: var(--muted);
            background: var(--bg-2); border: 1px solid var(--line);
            padding: 5px 10px; border-radius: 8px;
        }

        /* Stepper de cantidad pulido */
        .stepper {
            display: flex; align-items: center;
            background: var(--bg-2); border: 1px solid var(--line);
            border-radius: 11px; overflow: hidden; height: 44px;
            transition: var(--transition);
        }
        .stepper:focus-within { border-color: var(--gold-line); box-shadow: 0 0 0 3px var(--gold-dim); }
        .stepper.on { border-color: var(--gold-line); background: var(--gold-dim); }
        .step-btn {
            width: 42px; height: 100%; border: none; background: transparent;
            color: var(--gold-soft); font-size: 20px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: var(--transition);
        }
        .step-btn:hover { background: rgba(201,160,82,0.14); }
        .step-btn:active { transform: scale(0.88); }
        .step-val {
            width: 50px; height: 100%; text-align: center;
            font-size: 15.5px; font-weight: 700; color: var(--text);
            border: none; background: transparent; outline: none;
            -moz-appearance: textfield;
        }
        .step-val::-webkit-inner-spin-button, .step-val::-webkit-outer-spin-button { -webkit-appearance: none; }

        /* Skeleton */
        .sk {
            height: 150px; border-radius: var(--radius);
            background: var(--surface); border: 1px solid var(--line);
            position: relative; overflow: hidden;
        }
        .sk::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.04), transparent);
            animation: wave 1.5s infinite;
        }
        @keyframes wave { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

        .empty {
            grid-column: 1 / -1; text-align: center; padding: 48px 20px; color: var(--muted);
        }
        .empty svg { width: 40px; height: 40px; color: var(--muted-2); margin-bottom: 12px; }

        /* ── Order summary column ──────────────────── */
        .order-col {
            background: var(--surface);
            border-left: 1px solid var(--line);
            display: flex; flex-direction: column;
        }
        @media (min-width: 1080px) {
            .order-col { position: sticky; top: 0; height: 100vh; }
        }
        .order-head {
            padding: 20px 22px 14px; border-bottom: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between;
        }
        .order-head h3 { font-size: 17px; font-weight: 700; }
        .order-count {
            font-size: 12px; font-weight: 700; color: var(--gold-soft);
            background: var(--gold-dim); border: 1px solid var(--gold-line);
            padding: 3px 9px; border-radius: 999px;
        }
        .btn-close-sheet { display: none; }
        .order-body { flex: 1; overflow-y: auto; padding: 16px 22px; display: flex; flex-direction: column; gap: 18px; }
        .order-empty { text-align: center; color: var(--muted-2); padding: 40px 10px; font-size: 14px; }
        .order-empty svg { width: 44px; height: 44px; margin-bottom: 12px; opacity: 0.5; }

        .prov-group { display: flex; flex-direction: column; gap: 8px; }
        .prov-title {
            font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--gold-soft); padding-left: 9px; border-left: 2px solid var(--gold);
        }
        .oi {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            background: var(--bg-2); border: 1px solid var(--line);
            border-radius: var(--radius-sm); padding: 10px 12px;
        }
        .oi-info { min-width: 0; }
        .oi-name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .oi-sub { font-size: 11.5px; color: var(--muted-2); margin-top: 2px; }
        .oi-qty { font-size: 14px; font-weight: 700; color: var(--gold-soft); white-space: nowrap; }
        .btn-del {
            width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
            background: var(--danger-dim); border: 1px solid rgba(240,101,90,0.25); color: var(--danger);
            display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition);
        }
        .btn-del:hover { background: rgba(240,101,90,0.2); }
        .btn-del svg { width: 15px; height: 15px; }

        .note-box { display: flex; flex-direction: column; gap: 7px; }
        .note-head { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: var(--muted); }
        .note-area {
            width: 100%; min-height: 74px; resize: vertical;
            background: var(--bg-2); border: 1px solid var(--line); border-radius: var(--radius-sm);
            padding: 11px 13px; font-family: var(--font); font-size: 14px; color: var(--text); transition: var(--transition);
        }
        .note-area:focus { outline: none; border-color: var(--gold-line); box-shadow: 0 0 0 3px var(--gold-dim); }

        .order-foot { padding: 16px 22px 22px; border-top: 1px solid var(--line); }
        .order-totals { display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); margin-bottom: 12px; }
        .order-totals strong { color: var(--text); }
        .btn-send { width: 100%; height: 50px; font-size: 15.5px; }

        /* Floating cart bar (mobile) */
        .cart-bar {
            display: none;
            position: fixed; left: 16px; right: 16px; bottom: 16px; height: 60px;
            background: linear-gradient(135deg, var(--gold-h), #a87f33);
            border-radius: 15px; padding: 0 18px; z-index: 150;
            align-items: center; justify-content: space-between; cursor: pointer;
            box-shadow: 0 12px 28px rgba(201,160,82,0.4);
            transform: translateY(120px); transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        .cart-bar.visible { transform: translateY(0); }
        .cart-bar .cb-left { display: flex; align-items: center; gap: 11px; color: #1a1206; font-weight: 700; }
        .cart-bar .cb-badge {
            background: #1a1206; color: var(--gold-soft); min-width: 26px; height: 26px; padding: 0 7px;
            border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 13px;
        }
        .cart-bar .cb-right { display: flex; align-items: center; gap: 5px; color: #1a1206; font-weight: 700; }

        .sheet-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.62); z-index: 190;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .sheet-overlay.active { opacity: 1; pointer-events: auto; }

        /* ── Admin panel ───────────────────────────── */
        .admin-body { padding: 20px 30px 40px; display: flex; flex-direction: column; gap: 22px; }
        .stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px;
        }
        .stat {
            background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius);
            padding: 18px; display: flex; flex-direction: column; gap: 8px; position: relative; overflow: hidden;
        }
        .stat-ico {
            width: 40px; height: 40px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .stat-ico.gold { background: var(--gold-dim); border: 1px solid var(--gold-line); }
        .stat-ico.info { background: var(--info-dim); border: 1px solid rgba(96,165,250,0.3); }
        .stat-ico.success { background: var(--success-dim); border: 1px solid rgba(52,211,153,0.3); }
        .stat-ico.warn { background: var(--warn-dim); border: 1px solid rgba(251,191,36,0.3); }
        .stat-val { font-size: 30px; font-weight: 800; letter-spacing: -0.5px; line-height: 1; }
        .stat-label { font-size: 13px; color: var(--muted); font-weight: 500; }

        .orders-list { display: flex; flex-direction: column; gap: 13px; }
        .order-card {
            background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius);
            padding: 16px 18px; transition: var(--transition);
        }
        .order-card:hover { border-color: var(--line-2); }
        .oc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .oc-id { display: flex; flex-direction: column; gap: 3px; }
        .oc-author { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 9px; }
        .oc-author .who {
            width: 28px; height: 28px; border-radius: 8px; font-size: 13px; font-weight: 700;
            background: var(--gold-dim); border: 1px solid var(--gold-line); color: var(--gold-soft);
            display: flex; align-items: center; justify-content: center;
        }
        .oc-sub { font-size: 12.5px; color: var(--muted-2); padding-left: 37px; }
        .estado {
            font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 4px 11px; border-radius: 999px; white-space: nowrap;
        }
        .estado.enviado { background: var(--info-dim); color: var(--info); border: 1px solid rgba(96,165,250,0.3); }
        .estado.preparacion { background: var(--warn-dim); color: var(--warn); border: 1px solid rgba(251,191,36,0.3); }
        .estado.completado { background: var(--success-dim); color: var(--success); border: 1px solid rgba(52,211,153,0.3); }
        .estado.anulado { background: var(--surface-3); color: var(--muted-2); border: 1px solid var(--line-2); }
        .estado.error { background: var(--danger-dim); color: var(--danger); border: 1px solid rgba(240,101,90,0.3); }

        .oc-items {
            margin-top: 13px; padding-top: 13px; border-top: 1px solid var(--line);
            display: flex; flex-wrap: wrap; gap: 7px;
        }
        .item-pill {
            font-size: 12.5px; color: var(--muted); background: var(--bg-2);
            border: 1px solid var(--line); border-radius: 8px; padding: 5px 10px;
        }
        .item-pill b { color: var(--gold-soft); font-weight: 700; }
        .oc-note {
            margin-top: 11px; font-size: 13px; color: var(--muted); font-style: italic;
            background: var(--bg-2); border-left: 2px solid var(--gold-line); padding: 8px 12px; border-radius: 0 8px 8px 0;
        }
        .oc-foot {
            margin-top: 14px; padding-top: 13px; border-top: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        }
        .oc-totals { font-size: 13px; color: var(--muted); }
        .oc-totals strong { color: var(--text); font-weight: 700; }
        .oc-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-sm { padding: 7px 12px; font-size: 12.5px; border-radius: 9px; }
        .btn-sm svg { width: 14px; height: 14px; }
        .btn-prep:hover { border-color: var(--warn); color: var(--warn); }
        .btn-done:hover { border-color: var(--success); color: var(--success); }
        .btn-void:hover { border-color: var(--danger); color: var(--danger); }

        /* ── Alert modal ───────────────────────────── */
        .modal {
            position: fixed; inset: 0; background: rgba(0,0,0,0.82); z-index: 300;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .modal.active { opacity: 1; pointer-events: auto; }
        .modal-card {
            background: var(--surface); border: 1px solid var(--line); border-radius: 22px;
            padding: 34px 26px; width: 100%; max-width: 360px; text-align: center;
            transform: scale(0.92); transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.15);
            box-shadow: 0 24px 60px rgba(0,0,0,0.6);
        }
        .modal.active .modal-card { transform: scale(1); }
        .modal-ico {
            width: 62px; height: 62px; border-radius: 50%; margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center; font-size: 30px;
        }
        .modal-ico.success { background: var(--success-dim); color: var(--success); border: 1px solid rgba(52,211,153,0.3); }
        .modal-ico.error { background: var(--danger-dim); color: var(--danger); border: 1px solid rgba(240,101,90,0.3); }
        .modal-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .modal-desc { font-size: 14.5px; color: var(--muted); margin-bottom: 22px; line-height: 1.45; }
        .modal-btn {
            width: 100%; height: 47px; border-radius: 12px; cursor: pointer;
            background: var(--surface-2); border: 1px solid var(--line-2); color: var(--text);
            font-family: var(--font); font-size: 15px; font-weight: 600; transition: var(--transition);
        }
        .modal-btn:hover { background: var(--surface-3); }

        .spinner {
            width: 20px; height: 20px; border: 2px solid rgba(26,18,6,0.35);
            border-top-color: #1a1206; border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ────────────────────────────── */
        @media (max-width: 1024px) {
            .app { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed; top: 0; left: 0; z-index: 200; width: 280px;
                transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            }
            .sidebar.open { transform: translateX(0); box-shadow: 0 0 60px rgba(0,0,0,0.6); }
            .topbar-mobile { display: flex; }
            .toolbar { top: 71px; }
            .page-head, .toolbar, .catalog, .admin-body { padding-left: 18px; padding-right: 18px; }
        }
        @media (max-width: 1079px) {
            .cart-bar { display: flex; }
            .sheet-overlay { display: block; }
            .order-col {
                position: fixed; left: 0; right: 0; bottom: 0; z-index: 201;
                max-height: 86vh; border-radius: 24px 24px 0 0; border-left: none;
                border-top: 1px solid var(--line-2);
                transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
            }
            .order-col.active { transform: translateY(0); }
            .btn-close-sheet {
                display: flex; width: 34px; height: 34px; border-radius: 50%;
                background: var(--surface-2); border: none; color: var(--text);
                align-items: center; justify-content: center; cursor: pointer; font-size: 20px;
            }
            body.sheet-open { overflow: hidden; }
        }
        @media (max-width: 560px) {
            html { font-size: 14.5px; }
            .page-head { padding-top: 20px; }
            .page-head h2 { font-size: 21px; }
            .catalog { grid-template-columns: 1fr; padding-bottom: 96px; }
            .admin-body { padding-bottom: 40px; }
        }

        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 190;
        }
        .sidebar-overlay.active { display: block; }
    </style>
</head>
<body data-role="<?= $isAdmin ? 'admin' : 'staff' ?>">
    <div class="app">
        <!-- ── Sidebar ── -->
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-mark">M</div>
                <div class="brand-text">
                    <h1>MUHU</h1>
                    <p>Pedidos de Insumos</p>
                </div>
            </div>

            <div class="user-chip">
                <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
                <div class="user-meta">
                    <div class="name"><?= htmlspecialchars($userName) ?></div>
                    <span class="role-badge <?= $isAdmin ? 'admin' : 'staff' ?>"><?= $isAdmin ? 'Administrador' : 'Personal' ?></span>
                </div>
            </div>

            <nav class="nav">
                <div class="nav-label"><?= $isAdmin ? 'Gestión' : 'Pedidos' ?></div>
                <?php if ($isAdmin): ?>
                    <div class="nav-item active">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        <span>Pedidos en curso</span>
                    </div>
                    <a href="catalogo-admin.php" class="nav-item" style="text-decoration:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 3H8a2 2 0 0 0-2 2v2h12V5a2 2 0 0 0-2-2z"/></svg>
                        <span>Catálogo de insumos</span>
                    </a>
                <?php else: ?>
                    <div class="nav-item active" id="navNuevoPedido" onclick="switchStaffTab('nuevo')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span>Nuevo pedido</span>
                    </div>
                    <div class="nav-item" id="navMisPedidos" onclick="switchStaffTab('mis')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Mis pedidos</span>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="sidebar-spacer"></div>

            <form action="logout.php" method="POST">
                <button type="submit" class="btn-logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- ── Main ── -->
        <main class="main">
            <div class="topbar-mobile">
                <button class="hamburger" id="hamburger" aria-label="Menú">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <span class="tm-title"><?= $isAdmin ? 'Pedidos en curso' : 'Nuevo pedido' ?></span>
            </div>

<?php if ($isAdmin): ?>
            <!-- ════════ VISTA ADMIN ════════ -->
            <div class="page-head">
                <div>
                    <h2>Pedidos en curso</h2>
                    <p>Seguimiento de los pedidos del personal — estados y totales</p>
                </div>
                <button class="btn" id="btnRefresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    <span>Actualizar</span>
                </button>
            </div>

            <div class="toolbar">
                <div class="chips" id="estadoChips">
                    <div class="chip active" data-estado="curso">En curso</div>
                    <div class="chip" data-estado="todos">Todos</div>
                    <div class="chip" data-estado="enviado">Nuevos</div>
                    <div class="chip" data-estado="preparacion">En preparación</div>
                    <div class="chip" data-estado="completado">Completados</div>
                    <div class="chip" data-estado="anulado">Anulados</div>
                </div>
            </div>

            <div class="admin-body">
                <div class="stats" id="stats"></div>
                <div class="orders-list" id="ordersList">
                    <div class="sk"></div><div class="sk"></div><div class="sk"></div>
                </div>
            </div>

<?php else: ?>
            <!-- ════════ VISTA PERSONAL (crear pedido) ════════ -->
            <div class="page-head">
                <div>
                    <h2 id="staffPageTitle">Nuevo pedido</h2>
                    <p id="staffPageDesc">Selecciona los insumos y las cantidades que necesitas</p>
                </div>
            </div>

            <!-- ─── TAB: Nuevo pedido ─── -->
            <div id="tabNuevo" class="staff-body">
                <div class="catalog-col">
                    <div class="toolbar">
                        <div class="search-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="searchInput" class="search-input" placeholder="Buscar insumo por nombre...">
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <div class="chips" id="categoriesContainer">
                                <div class="chip active" data-category="Todos">Todos</div>
                            </div>
                            <button class="btn" id="btnItemLibre" style="white-space:nowrap;gap:6px;padding:0 14px;height:36px;font-size:0.82rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Ítem libre
                            </button>
                        </div>
                    </div>
                    <div class="catalog" id="catalogContainer">
                        <div class="sk"></div><div class="sk"></div><div class="sk"></div><div class="sk"></div>
                    </div>
                </div>

                <!-- Resumen del pedido (columna escritorio / hoja móvil) -->
                <aside class="order-col" id="orderCol">
                    <div class="order-head">
                        <h3>Tu pedido</h3>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="order-count" id="orderCount">0 ítems</span>
                            <button class="btn-close-sheet" id="btnCloseSheet">&times;</button>
                        </div>
                    </div>
                    <div class="order-body" id="orderBody">
                        <div class="order-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <p>Aún no has agregado insumos.</p>
                        </div>
                    </div>
                    <div class="order-foot" id="orderFoot" style="display:none;">
                        <div class="note-box" style="margin-bottom:14px;">
                            <div class="note-head"><span>Notas especiales</span><span id="charCount">0 / 300</span></div>
                            <textarea id="noteTextarea" class="note-area" placeholder="Instrucciones adicionales para el pedido..." maxlength="300"></textarea>
                        </div>
                        <div class="order-totals">
                            <span>Total</span>
                            <span><strong id="totLines">0</strong> insumos · <strong id="totUnits">0</strong> unidades</span>
                        </div>
                        <button class="btn btn-gold btn-send" id="btnSendOrder">
                            <span id="btnSendText">Confirmar y enviar</span>
                            <div class="spinner" id="btnSendSpinner" style="display:none;"></div>
                        </button>
                    </div>
                </aside>
            </div>
            <!-- FIN TAB: Nuevo pedido -->

            <!-- TAB: Mis pedidos -->
            <div id="tabMis" style="display:none;">
                <div class="admin-body">
                    <div class="orders-list" id="misPedidosList">
                        <div class="sk"></div><div class="sk"></div><div class="sk"></div>
                    </div>
                </div>
            </div>

            <!-- Modal ítem libre -->
            <div class="modal" id="modalItemLibre">
                <div class="modal-card">
                    <div class="modal-ico" style="font-size:1.5rem;">✏️</div>
                    <h3 class="modal-title">Agregar ítem libre</h3>
                    <p class="modal-desc" style="margin-bottom:14px;">Escribe el nombre del producto y la cantidad. Se enviará tal cual al administrador.</p>
                    <input id="itemLibreNombre" type="text" class="search-input" placeholder="Nombre del producto (ej. Azúcar rubia)" style="margin-bottom:10px;width:100%;" maxlength="80">
                    <input id="itemLibreCantidad" type="number" class="search-input" placeholder="Cantidad (ej. 5)" min="0.01" step="0.01" style="margin-bottom:10px;width:100%;">
                    <input id="itemLibreUnidad" type="text" class="search-input" placeholder="Unidad (ej. kg, litro, bolsa)" style="margin-bottom:18px;width:100%;" maxlength="20">
                    <div style="display:flex;gap:10px;">
                        <button class="btn" id="btnItemLibreCancel" style="flex:1;">Cancelar</button>
                        <button class="btn btn-gold" id="btnItemLibreOk" style="flex:1;">Agregar al pedido</button>
                    </div>
                </div>
            </div>

            <!-- Barra flotante (móvil) -->
            <div class="cart-bar" id="cartBar">
                <div class="cb-left">
                    <span class="cb-badge" id="cartCount">0</span>
                    <span>Revisar pedido</span>
                </div>
                <div class="cb-right">
                    <span>Siguiente</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </div>
            </div>
            <div class="sheet-overlay" id="sheetOverlay"></div>
<?php endif; ?>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="alertModal">
        <div class="modal-card">
            <div class="modal-ico" id="alertIcon">✓</div>
            <h3 class="modal-title" id="alertTitle">Estado</h3>
            <p class="modal-desc" id="alertDesc"></p>
            <button class="modal-btn" id="btnAlertClose">Entendido</button>
        </div>
    </div>

    <script>
        const ROLE = document.body.dataset.role;

        // ── Inactividad (15 min) ──
        let inactivityTimeout;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimeout);
            inactivityTimeout = setTimeout(() => { window.location.href = 'logout.php'; }, 900000);
        }
        ['mousedown', 'touchstart', 'keydown', 'scroll'].forEach(e =>
            window.addEventListener(e, resetInactivityTimer, { passive: true }));
        resetInactivityTimer();

        // ── Sidebar móvil ──
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburger');
        function toggleSidebar(open) {
            sidebar.classList.toggle('open', open);
            sidebarOverlay.classList.toggle('active', open);
        }
        if (hamburger) hamburger.addEventListener('click', () => toggleSidebar(true));
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', () => toggleSidebar(false));

        // ── Modal ──
        const alertModal = document.getElementById('alertModal');
        const alertIcon = document.getElementById('alertIcon');
        const alertTitle = document.getElementById('alertTitle');
        const alertDesc = document.getElementById('alertDesc');
        document.getElementById('btnAlertClose').addEventListener('click', () => alertModal.classList.remove('active'));
        function showAlert(success, title, msg) {
            alertIcon.className = 'modal-ico ' + (success ? 'success' : 'error');
            alertIcon.textContent = success ? '✓' : '⚠';
            alertTitle.textContent = title;
            alertDesc.textContent = msg;
            alertModal.classList.add('active');
        }

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c =>
                ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }
        const fmtNum = n => (Math.round(Number(n) * 100) / 100).toLocaleString('es-PE');

        // ════════════════════════════════════════════
        //  Catálogo compartido
        // ════════════════════════════════════════════
        let catalog = [];
        const catMap = {};
        async function loadCatalog() {
            const r = await fetch('api.php?action=catalogo');
            if (r.status === 401) { window.location.href = 'login.php'; throw new Error('401'); }
            if (!r.ok) throw new Error('No se pudo cargar el catálogo.');
            const data = await r.json();
            catalog = data.items || [];
            catalog.forEach(i => { catMap[i.codigo] = i; });
            return catalog;
        }

        if (ROLE === 'staff') { initStaff(); } else { initAdmin(); }

        // ════════════════════════════════════════════
        //  VISTA PERSONAL — crear pedido
        // ════════════════════════════════════════════
        function initStaff() {
            const cart = {};
            let currentCategory = 'Todos';
            let searchQuery = '';
            let requestId = crypto.randomUUID();

            const catalogContainer = document.getElementById('catalogContainer');
            const categoriesContainer = document.getElementById('categoriesContainer');
            const searchInput = document.getElementById('searchInput');
            const orderCol = document.getElementById('orderCol');
            const orderBody = document.getElementById('orderBody');
            const orderFoot = document.getElementById('orderFoot');
            const orderCount = document.getElementById('orderCount');
            const totLines = document.getElementById('totLines');
            const totUnits = document.getElementById('totUnits');
            const noteTextarea = document.getElementById('noteTextarea');
            const charCount = document.getElementById('charCount');
            const btnSendOrder = document.getElementById('btnSendOrder');
            const btnSendText = document.getElementById('btnSendText');
            const btnSendSpinner = document.getElementById('btnSendSpinner');
            const cartBar = document.getElementById('cartBar');
            const cartCount = document.getElementById('cartCount');
            const sheetOverlay = document.getElementById('sheetOverlay');
            const btnCloseSheet = document.getElementById('btnCloseSheet');

            const stepFor = u => {
                const l = (u || '').toLowerCase();
                return ['kg', 'kilo', 'kilos', 'litro', 'litros', 'l'].includes(l) ? 0.5 : 1;
            };

            async function fetchCatalog() {
                try {
                    await loadCatalog();
                    renderCategories();
                    renderCatalog();
                } catch (err) {
                    if (err.message === '401') return;
                    catalogContainer.innerHTML =
                        `<div class="empty" style="color:var(--danger)">
                            <p style="font-weight:600;margin-bottom:14px;">No se pudo conectar con el catálogo.</p>
                            <button class="btn" onclick="location.reload()">Reintentar</button>
                        </div>`;
                }
            }

            function renderCategories() {
                const cats = new Set(['Todos']);
                catalog.forEach(i => { if (i.categoria) cats.add(i.categoria); });
                categoriesContainer.innerHTML = Array.from(cats).map(c =>
                    `<div class="chip ${c === currentCategory ? 'active' : ''}" data-category="${escapeHtml(c)}">${escapeHtml(c)}</div>`
                ).join('');
                categoriesContainer.querySelectorAll('.chip').forEach(chip => {
                    chip.addEventListener('click', () => {
                        categoriesContainer.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
                        chip.classList.add('active');
                        currentCategory = chip.dataset.category;
                        renderCatalog();
                    });
                });
            }

            function renderCatalog() {
                const q = searchQuery.toLowerCase();
                const filtered = catalog.filter(i =>
                    (i.nombre || '').toLowerCase().includes(q) &&
                    (currentCategory === 'Todos' || i.categoria === currentCategory));

                if (!filtered.length) {
                    catalogContainer.innerHTML =
                        `<div class="empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <p>No se encontraron insumos.</p>
                        </div>`;
                    return;
                }

                catalogContainer.innerHTML = filtered.map(i => {
                    const v = cart[i.codigo] || 0;
                    const on = v > 0;
                    const cod = escapeHtml(i.codigo);
                    return `
                        <div class="product-card ${on ? 'has-items' : ''}" id="card-${cod}">
                            <div class="pc-top">
                                <span class="pc-tag">${escapeHtml(i.categoria || 'Otros')}</span>
                                <h3 class="pc-name">${escapeHtml(i.nombre)}</h3>
                                <div class="pc-meta">
                                    <span>${escapeHtml(i.proveedor || 'Sin proveedor')}</span>
                                    <span class="pc-dot"></span>
                                    <span>${escapeHtml(i.unidad || 'und')}</span>
                                </div>
                            </div>
                            <div class="pc-bottom">
                                <span class="unit-badge">${escapeHtml(i.unidad || 'und')}</span>
                                <div class="stepper ${on ? 'on' : ''}" id="step-${cod}">
                                    <button class="step-btn" onclick="adjustCount('${cod}',-1)">−</button>
                                    <input class="step-val" id="val-${cod}" type="number" inputmode="decimal"
                                           value="${v}" min="0" step="${stepFor(i.unidad)}"
                                           onchange="setCount('${cod}', this.value)">
                                    <button class="step-btn" onclick="adjustCount('${cod}',1)">+</button>
                                </div>
                            </div>
                        </div>`;
                }).join('');
            }

            function syncCard(codigo) {
                const v = cart[codigo] || 0;
                const card = document.getElementById(`card-${codigo}`);
                const step = document.getElementById(`step-${codigo}`);
                const input = document.getElementById(`val-${codigo}`);
                if (card) card.classList.toggle('has-items', v > 0);
                if (step) step.classList.toggle('on', v > 0);
                if (input) input.value = v;
            }

            window.adjustCount = (codigo, dir) => {
                const item = catMap[codigo];
                const step = stepFor(item && item.unidad);
                let v = (cart[codigo] || 0) + dir * step;
                v = Math.round(v * 100) / 100;
                setCount(codigo, v);
            };

            window.setCount = (codigo, value) => {
                let v = parseFloat(value);
                if (isNaN(v) || v <= 0) { delete cart[codigo]; v = 0; }
                else { v = Math.round(v * 100) / 100; cart[codigo] = v; }
                syncCard(codigo);
                updateOrder();
            };

            function updateOrder() {
                const codes = Object.keys(cart);
                const lines = codes.length;
                const units = codes.reduce((s, c) => s + cart[c], 0);

                orderCount.textContent = `${lines} ${lines === 1 ? 'ítem' : 'ítems'}`;
                cartCount.textContent = lines;
                cartBar.classList.toggle('visible', lines > 0);
                totLines.textContent = lines;
                totUnits.textContent = fmtNum(units);
                orderFoot.style.display = lines > 0 ? 'block' : 'none';

                if (!lines) {
                    orderBody.innerHTML =
                        `<div class="order-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <p>Aún no has agregado insumos.</p>
                        </div>`;
                    return;
                }

                const groups = {};
                codes.forEach(c => {
                    const p = catMap[c];
                    const prov = (p && p.proveedor) || 'Distribuidor general';
                    (groups[prov] = groups[prov] || []).push({ ...(p || { codigo: c, nombre: c }), cantidad: cart[c] });
                });

                orderBody.innerHTML = Object.entries(groups).map(([prov, items]) => `
                    <div class="prov-group">
                        <div class="prov-title">${escapeHtml(prov)}</div>
                        ${items.map(it => `
                            <div class="oi">
                                <div class="oi-info">
                                    <div class="oi-name">${escapeHtml(it.nombre)}</div>
                                    <div class="oi-sub">${escapeHtml(it.codigo)}</div>
                                </div>
                                <span class="oi-qty">${fmtNum(it.cantidad)} ${escapeHtml(it.unidad || 'und')}</span>
                                <button class="btn-del" onclick="removeItem('${escapeHtml(it.codigo)}')" aria-label="Quitar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>`).join('')}
                    </div>`).join('');
            }

            window.removeItem = (codigo) => {
                delete cart[codigo];
                syncCard(codigo);
                updateOrder();
                if (!Object.keys(cart).length) closeSheet();
            };

            // Hoja móvil
            function openSheet() {
                if (!Object.keys(cart).length) return;
                orderCol.classList.add('active');
                sheetOverlay.classList.add('active');
                document.body.classList.add('sheet-open');
            }
            function closeSheet() {
                orderCol.classList.remove('active');
                sheetOverlay.classList.remove('active');
                document.body.classList.remove('sheet-open');
            }
            cartBar.addEventListener('click', openSheet);
            sheetOverlay.addEventListener('click', closeSheet);
            btnCloseSheet.addEventListener('click', closeSheet);

            searchInput.addEventListener('input', e => { searchQuery = e.target.value; renderCatalog(); });
            noteTextarea.addEventListener('input', e => { charCount.textContent = `${e.target.value.length} / 300`; });

            btnSendOrder.addEventListener('click', async () => {
                const items = Object.entries(cart).map(([codigo, cantidad]) => ({ codigo, cantidad }));
                if (!items.length) return;

                btnSendOrder.disabled = true;
                btnSendText.style.display = 'none';
                btnSendSpinner.style.display = 'block';

                try {
                    const r = await fetch('api.php?action=enviar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id: requestId, nota: noteTextarea.value, items })
                    });
                    const data = await r.json();
                    if (r.ok && data.success) {
                        if (data.duplicado) {
                            showAlert(true, 'Pedido ya recibido', `El pedido folio ${data.folio} ya había sido procesado.`);
                        } else {
                            showAlert(true, 'Pedido enviado', `Registrado con éxito. Folio: ${data.folio ?? '—'}.`);
                        }
                        Object.keys(cart).forEach(k => delete cart[k]);
                        noteTextarea.value = '';
                        charCount.textContent = '0 / 300';
                        requestId = crypto.randomUUID();
                        updateOrder();
                        renderCatalog();
                        closeSheet();
                    } else {
                        throw new Error(data.error || 'Ocurrió un error en el servidor central.');
                    }
                } catch (err) {
                    showAlert(false, 'Error al enviar', `${err.message} Vuelve a intentarlo.`);
                } finally {
                    btnSendOrder.disabled = false;
                    btnSendText.style.display = 'block';
                    btnSendSpinner.style.display = 'none';
                }
            });

            fetchCatalog();

            // ── Ítem libre ──
            const modalItemLibre = document.getElementById('modalItemLibre');
            document.getElementById('btnItemLibre').addEventListener('click', () => {
                document.getElementById('itemLibreNombre').value = '';
                document.getElementById('itemLibreCantidad').value = '';
                document.getElementById('itemLibreUnidad').value = '';
                modalItemLibre.classList.add('active');
                setTimeout(() => document.getElementById('itemLibreNombre').focus(), 80);
            });
            document.getElementById('btnItemLibreCancel').addEventListener('click', () => modalItemLibre.classList.remove('active'));
            document.getElementById('btnItemLibreOk').addEventListener('click', () => {
                const nombre   = document.getElementById('itemLibreNombre').value.trim();
                const cantidad = parseFloat(document.getElementById('itemLibreCantidad').value);
                const unidad   = document.getElementById('itemLibreUnidad').value.trim() || 'und';
                if (!nombre)             { document.getElementById('itemLibreNombre').focus();   return; }
                if (!cantidad || cantidad <= 0) { document.getElementById('itemLibreCantidad').focus(); return; }
                const codigo = 'LIBRE-' + Date.now().toString(36).toUpperCase().slice(-5);
                catMap[codigo] = { nombre, unidad, categoria: 'Libre' };
                addToCart({ codigo, nombre, unidad, categoria: 'Libre' }, cantidad);
                modalItemLibre.classList.remove('active');
            });
        }

        // ── Switch tabs staff ──
        window.switchStaffTab = function(tab) {
            const navNuevo = document.getElementById('navNuevoPedido');
            const navMis   = document.getElementById('navMisPedidos');
            const tabNuevo = document.getElementById('tabNuevo');
            const tabMis   = document.getElementById('tabMis');
            const title    = document.getElementById('staffPageTitle');
            const desc     = document.getElementById('staffPageDesc');
            if (tab === 'nuevo') {
                navNuevo.classList.add('active');   navMis.classList.remove('active');
                tabNuevo.style.display = '';        tabMis.style.display = 'none';
                title.textContent = 'Nuevo pedido';
                desc.textContent  = 'Selecciona los insumos y las cantidades que necesitas';
            } else {
                navMis.classList.add('active');     navNuevo.classList.remove('active');
                tabNuevo.style.display = 'none';    tabMis.style.display = '';
                title.textContent = 'Mis pedidos';
                desc.textContent  = 'Historial de pedidos que has enviado';
                loadMisPedidos();
            }
        };

        async function loadMisPedidos() {
            const el = document.getElementById('misPedidosList');
            if (!el) return;
            el.innerHTML = '<div class="sk"></div><div class="sk"></div>';
            try {
                await loadCatalog().catch(() => {});
                const r = await fetch('api.php?action=mis-pedidos');
                if (r.status === 401) { window.location.href = 'login.php'; return; }
                const data = await r.json();
                const pedidos = data.pedidos || [];
                const ESTADO_LABEL = { enviado: 'Enviado', preparacion: 'En preparación', completado: 'Completado', anulado: 'Anulado', error: 'Error' };
                const ESTADO_CLS   = { enviado: 'enviado', preparacion: 'preparacion', completado: 'completado', anulado: 'anulado', error: 'error' };
                const fmtDate = iso => { try { return new Date(iso).toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); } catch { return iso; } };
                if (!pedidos.length) {
                    el.innerHTML = `<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>Aún no tienes pedidos enviados.</p></div>`;
                    return;
                }
                el.innerHTML = pedidos.map(p => {
                    const est   = p.estado || 'enviado';
                    const items = (p.items || []).map(it => {
                        const prod = catMap[it.codigo];
                        const name = prod ? prod.nombre : it.codigo;
                        const unit = prod ? (prod.unidad || 'und') : '';
                        return `<span class="item-pill"><b>${fmtNum(it.cantidad)} ${escapeHtml(unit)}</b> · ${escapeHtml(name)}</span>`;
                    }).join('');
                    return `<div class="order-card">
                        <div class="oc-head">
                            <div class="oc-id">
                                <div class="oc-sub">${p.folio ? 'Folio ' + escapeHtml(String(p.folio)) + ' · ' : ''}${fmtDate(p.creado_en)}</div>
                            </div>
                            <span class="estado ${ESTADO_CLS[est] || est}">${ESTADO_LABEL[est] || est}</span>
                        </div>
                        <div class="oc-items">${items || '<span class="oc-totals">Sin ítems</span>'}</div>
                        ${p.nota ? `<div class="oc-note">"${escapeHtml(p.nota)}"</div>` : ''}
                        <div class="oc-foot">
                            <div class="oc-totals"><strong>${p.total_lineas ?? (p.items || []).length}</strong> insumos · <strong>${fmtNum(p.total_unidades || 0)}</strong> unidades</div>
                        </div>
                    </div>`;
                }).join('');
            } catch (err) {
                el.innerHTML = `<div class="empty" style="color:var(--danger)"><p>${escapeHtml(err.message)}</p></div>`;
            }
        }

        // ════════════════════════════════════════════
        //  VISTA ADMIN — gestión de pedidos
        // ════════════════════════════════════════════
        function initAdmin() {
            const statsEl = document.getElementById('stats');
            const ordersEl = document.getElementById('ordersList');
            const btnRefresh = document.getElementById('btnRefresh');
            const estadoChips = document.getElementById('estadoChips');
            let pedidos = [];
            let filtro = 'curso';
            let pollTimer = null;

            const ESTADO_LABEL = { enviado: 'Nuevo', preparacion: 'En preparación', completado: 'Completado', anulado: 'Anulado', error: 'Error de envío' };
            const isToday = iso => { const d = new Date(iso); const n = new Date(); return d.toDateString() === n.toDateString(); };
            const fmtDate = iso => { try { return new Date(iso).toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); } catch { return iso; } };

            estadoChips.querySelectorAll('.chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    estadoChips.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    filtro = chip.dataset.estado;
                    renderOrders();
                });
            });

            async function loadPedidos() {
                try { await loadCatalog(); } catch (e) { /* catálogo opcional para nombres */ }
                const r = await fetch('api.php?action=pedidos');
                if (r.status === 401) { window.location.href = 'login.php'; return; }
                if (r.status === 403) { ordersEl.innerHTML = `<div class="empty">Acceso restringido.</div>`; return; }
                if (!r.ok) throw new Error('No se pudieron cargar los pedidos.');
                const data = await r.json();
                pedidos = data.pedidos || [];
                renderStats();
                renderOrders();
            }

            function renderStats() {
                const enCurso = pedidos.filter(p => p.estado === 'enviado' || p.estado === 'preparacion').length;
                const nuevos = pedidos.filter(p => p.estado === 'enviado').length;
                const compHoy = pedidos.filter(p => p.estado === 'completado' && isToday(p.actualizado_en || p.creado_en)).length;
                const unidadesHoy = pedidos.filter(p => isToday(p.creado_en))
                    .reduce((s, p) => s + (Number(p.total_unidades) || 0), 0);

                const card = (ico, cls, val, label) => `
                    <div class="stat">
                        <div class="stat-ico ${cls}">${ico}</div>
                        <div class="stat-val">${val}</div>
                        <div class="stat-label">${label}</div>
                    </div>`;
                statsEl.innerHTML =
                    card('📋', 'gold', enCurso, 'Pedidos en curso') +
                    card('🆕', 'info', nuevos, 'Nuevos por revisar') +
                    card('✅', 'success', compHoy, 'Completados hoy') +
                    card('📦', 'warn', fmtNum(unidadesHoy), 'Unidades pedidas hoy');
            }

            function matchFilter(p) {
                if (filtro === 'todos') return true;
                if (filtro === 'curso') return p.estado === 'enviado' || p.estado === 'preparacion' || p.estado === 'error';
                if (filtro === 'anulado') return p.estado === 'anulado' || p.estado === 'error';
                return p.estado === filtro;
            }

            function renderOrders() {
                const list = pedidos.filter(matchFilter);
                if (!list.length) {
                    ordersEl.innerHTML =
                        `<div class="empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <p>No hay pedidos en esta vista.</p>
                        </div>`;
                    return;
                }

                ordersEl.innerHTML = list.map(p => {
                    const est = p.estado || 'enviado';
                    const items = (p.items || []).map(it => {
                        const prod = catMap[it.codigo];
                        const name = prod ? prod.nombre : it.codigo;
                        const unit = prod ? (prod.unidad || 'und') : '';
                        return `<span class="item-pill"><b>${fmtNum(it.cantidad)} ${escapeHtml(unit)}</b> · ${escapeHtml(name)}</span>`;
                    }).join('');
                    const initial = escapeHtml((p.autor || '?').charAt(0).toUpperCase());
                    const acts = [];
                    if (est === 'enviado') acts.push(`<button class="btn btn-sm btn-prep" onclick="setEstado('${p.request_id}','preparacion')">Marcar en preparación</button>`);
                    if (est === 'enviado' || est === 'preparacion') acts.push(`<button class="btn btn-sm btn-done" onclick="setEstado('${p.request_id}','completado')">Completar</button>`);
                    if (est !== 'anulado' && est !== 'completado') acts.push(`<button class="btn btn-sm btn-void" onclick="setEstado('${p.request_id}','anulado')">Anular</button>`);
                    if (est === 'completado' || est === 'anulado') acts.push(`<button class="btn btn-sm" onclick="setEstado('${p.request_id}','enviado')">Reabrir</button>`);

                    return `
                        <div class="order-card">
                            <div class="oc-head">
                                <div class="oc-id">
                                    <div class="oc-author"><span class="who">${initial}</span>${escapeHtml(p.autor || 'Personal')}</div>
                                    <div class="oc-sub">${p.folio ? 'Folio ' + escapeHtml(String(p.folio)) + ' · ' : ''}${fmtDate(p.creado_en)}</div>
                                </div>
                                <span class="estado ${est}">${ESTADO_LABEL[est] || est}</span>
                            </div>
                            <div class="oc-items">${items || '<span class="oc-totals">Sin ítems</span>'}</div>
                            ${p.nota ? `<div class="oc-note">“${escapeHtml(p.nota)}”</div>` : ''}
                            <div class="oc-foot">
                                <div class="oc-totals"><strong>${p.total_lineas ?? (p.items || []).length}</strong> insumos · <strong>${fmtNum(p.total_unidades || 0)}</strong> unidades</div>
                                <div class="oc-actions">${acts.join('')}</div>
                            </div>
                        </div>`;
                }).join('');
            }

            window.setEstado = async (request_id, estado) => {
                try {
                    const r = await fetch('api.php?action=estado', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id, estado })
                    });
                    const data = await r.json();
                    if (r.ok && data.success) {
                        const p = pedidos.find(x => x.request_id === request_id);
                        if (p) { p.estado = estado; p.actualizado_en = new Date().toISOString(); }
                        renderStats();
                        renderOrders();
                    } else {
                        throw new Error(data.error || 'No se pudo actualizar.');
                    }
                } catch (err) {
                    showAlert(false, 'Error', err.message);
                }
            };

            async function refresh() {
                try { await loadPedidos(); }
                catch (err) { ordersEl.innerHTML = `<div class="empty" style="color:var(--danger)"><p>${escapeHtml(err.message)}</p></div>`; }
            }

            btnRefresh.addEventListener('click', refresh);
            refresh();
            pollTimer = setInterval(refresh, 25000);
        }
    </script>
</body>
</html>
