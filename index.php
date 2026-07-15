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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pedidos Muhu - Cocina & Insumos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0c0a09;
            --card-bg: #1c1917;
            --text-color: #fafaf9;
            --text-muted: #a8a29e;
            --primary: #d97706; /* Muhu Gold */
            --primary-hover: #b45309;
            --danger: #ef4444;
            --success: #10b981;
            --input-bg: #292524;
            --input-border: #44403c;
            --font-family: 'Outfit', sans-serif;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --header-height: 70px;
            --cart-bar-height: 80px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-color);
            color: var(--text-color);
            overflow-x: hidden;
            padding-top: var(--header-height);
            padding-bottom: calc(var(--cart-bar-height) + 20px);
            min-height: 100vh;
        }

        /* Ambient background glow */
        .bg-glow {
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(217, 119, 6, 0.08) 0%, transparent 70%);
            top: 20%;
            right: -100px;
            z-index: -1;
            filter: blur(50px);
            pointer-events: none;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background-color: rgba(28, 25, 23, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #2e2a24;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 16px;
            z-index: 100;
        }

        .header-brand {
            display: flex;
            flex-direction: column;
        }
        .header-title {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header-user {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .btn-logout {
            height: 40px;
            padding: 0 12px;
            border: 1px solid #44403c;
            border-radius: 10px;
            background-color: transparent;
            color: var(--text-color);
            font-family: var(--font-family);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }
        .btn-logout:hover {
            border-color: var(--danger);
            color: var(--danger);
            background-color: rgba(239, 68, 68, 0.05);
        }

        /* Sticky Search & Filter Section */
        .sticky-section {
            position: sticky;
            top: var(--header-height);
            background-color: rgba(12, 10, 9, 0.9);
            backdrop-filter: blur(8px);
            padding: 12px 16px;
            z-index: 90;
            border-bottom: 1px solid #1c1917;
        }

        .search-container {
            position: relative;
            margin-bottom: 12px;
        }
        .search-input {
            width: 100%;
            height: 48px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 0 16px 0 44px;
            font-family: var(--font-family);
            font-size: 16px;
            color: var(--text-color);
            transition: var(--transition);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.1);
        }
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        /* Category chips */
        .categories-container {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none; /* Firefox */
        }
        .categories-container::-webkit-scrollbar {
            display: none; /* Safari/Chrome */
        }
        .category-chip {
            padding: 8px 16px;
            border-radius: 9999px;
            background-color: var(--card-bg);
            border: 1px solid #2e2a24;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: var(--transition);
        }
        .category-chip.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 4px 10px rgba(217, 119, 6, 0.25);
        }

        /* Catalog Grid */
        .catalog-container {
            padding: 16px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        @media (min-width: 600px) {
            .catalog-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .product-card {
            background-color: var(--card-bg);
            border: 1px solid #2e2a24;
            border-radius: 18px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: var(--transition);
        }
        .product-card:hover {
            border-color: #44403c;
        }
        .product-card.has-items {
            border-color: rgba(217, 119, 6, 0.4);
            box-shadow: inset 0 0 0 1px rgba(217, 119, 6, 0.15);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .product-tag {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .product-name {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-color);
        }
        .product-meta {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .product-meta-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background-color: #44403c;
        }

        /* Count Controls */
        .controls-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .unit-badge {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            background-color: rgba(255, 255, 255, 0.03);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #2e2a24;
        }
        .count-controls {
            display: flex;
            align-items: center;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            overflow: hidden;
        }
        .btn-ctrl {
            width: 48px;
            height: 48px;
            border: none;
            background-color: transparent;
            color: var(--text-color);
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: var(--transition);
        }
        .btn-ctrl:active {
            background-color: rgba(255, 255, 255, 0.05);
            transform: scale(0.9);
        }
        .count-value {
            width: 54px;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: var(--text-color);
            border: none;
            background-color: transparent;
            outline: none;
            -moz-appearance: textfield;
        }
        .count-value::-webkit-inner-spin-button,
        .count-value::-webkit-outer-spin-button {
            -webkit-appearance: none;
        }

        /* Loading Skeleton */
        .skeleton-card {
            height: 120px;
            background-color: var(--card-bg);
            border: 1px solid #2e2a24;
            border-radius: 18px;
            position: relative;
            overflow: hidden;
        }
        .skeleton-card::after {
            content: "";
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            animation: skeleton-wave 1.5s infinite;
        }
        @keyframes skeleton-wave {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Empty Catalog message */
        .empty-catalog {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        /* Floating Cart Bar */
        .cart-bar {
            position: fixed;
            bottom: 16px;
            left: 16px;
            right: 16px;
            height: 64px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 10px 25px rgba(217, 119, 6, 0.35);
            z-index: 95;
            cursor: pointer;
            transform: translateY(120px);
            transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        .cart-bar.visible {
            transform: translateY(0);
        }
        .cart-bar-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cart-badge {
            background-color: #fff;
            color: var(--primary-hover);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            font-weight: 700;
        }
        .cart-bar-text {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }
        .cart-bar-action {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Slide-up Cart Panel Overlay */
        .panel-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 200;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .panel-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* Slide-up Panel sheet */
        .cart-panel {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            max-height: 85vh;
            background-color: #171513;
            border-top: 1px solid #2e2a24;
            border-top-left-radius: 28px;
            border-top-right-radius: 28px;
            z-index: 201;
            transform: translateY(100%);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }
        .cart-panel.active {
            transform: translateY(0);
        }

        .panel-header {
            padding: 20px 20px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2e2a24;
        }
        .panel-title {
            font-size: 20px;
            font-weight: 700;
        }
        .btn-close-panel {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #292524;
            border: none;
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            font-size: 18px;
        }

        /* Cart Content List scrollable */
        .panel-body {
            padding: 16px 20px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .provider-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .provider-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 3px solid var(--primary);
            padding-left: 8px;
        }

        .cart-item {
            background-color: rgba(255,255,255,0.02);
            border: 1px solid #252220;
            border-radius: 12px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .cart-item-info {
            flex: 1;
        }
        .cart-item-name {
            font-size: 15px;
            font-weight: 600;
        }
        .cart-item-unit {
            font-size: 12px;
            color: var(--text-muted);
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-delete-item {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-delete-item:active {
            transform: scale(0.9);
        }

        /* Order note section */
        .note-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .note-textarea {
            width: 100%;
            height: 80px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 12px;
            font-family: var(--font-family);
            font-size: 15px;
            color: var(--text-color);
            resize: none;
            transition: var(--transition);
        }
        .note-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Panel footer actions */
        .panel-footer {
            padding: 16px 20px 24px;
            border-top: 1px solid #2e2a24;
            background-color: #171513;
        }
        .btn-send-order {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-family: var(--font-family);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.25);
            transition: var(--transition);
        }
        .btn-send-order:active {
            transform: translateY(1px);
        }
        .btn-send-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Modal screens (Success/Error Alerts) */
        .alert-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.85);
            z-index: 300;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .alert-modal.active {
            opacity: 1;
            pointer-events: auto;
        }

        .alert-card {
            background-color: var(--card-bg);
            border: 1px solid #2e2a24;
            border-radius: 24px;
            padding: 36px 24px;
            width: 100%;
            max-width: 360px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.15);
        }
        .alert-modal.active .alert-card {
            transform: scale(1);
        }

        .alert-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 32px;
            margin: 0 auto 20px;
        }
        .alert-icon.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .alert-icon.error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .alert-desc {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 24px;
            line-height: 1.4;
        }
        .btn-alert-close {
            width: 100%;
            height: 48px;
            background-color: #292524;
            border: 1px solid #44403c;
            border-radius: 12px;
            color: var(--text-color);
            font-family: var(--font-family);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-alert-close:hover {
            background-color: #3e3a36;
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>

    <!-- Header -->
    <header>
        <div class="header-brand">
            <span class="header-title">MUHU</span>
            <span class="header-user"><?= htmlspecialchars($_SESSION['user']['name']) ?></span>
        </div>
        <form action="logout.php" method="POST">
            <button type="submit" class="btn-logout">
                <span>Salir</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            </button>
        </form>
    </header>

    <!-- Sticky Search & Filter -->
    <div class="sticky-section">
        <div class="search-container">
            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar insumo por nombre...">
        </div>
        <div class="categories-container" id="categoriesContainer">
            <div class="category-chip active" data-category="Todos">Todos</div>
        </div>
    </div>

    <!-- Catalog container -->
    <div class="catalog-container" id="catalogContainer">
        <!-- Skeleton loaders initially -->
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
    </div>

    <!-- Floating Cart Bar -->
    <div class="cart-bar" id="cartBar">
        <div class="cart-bar-info">
            <div class="cart-badge" id="cartCount">0</div>
            <div class="cart-bar-text">Revisar pedido</div>
        </div>
        <div class="cart-bar-action">
            <span>Siguiente</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </div>
    </div>

    <!-- Slide-up Cart Panel Overlay -->
    <div class="panel-overlay" id="panelOverlay"></div>

    <!-- Slide-up Cart Panel Sheet -->
    <div class="cart-panel" id="cartPanel">
        <div class="panel-header">
            <h2 class="panel-title">Revisar Solicitud</h2>
            <button class="btn-close-panel" id="btnClosePanel">&times;</button>
        </div>
        <div class="panel-body">
            <!-- Selected items grouped by provider -->
            <div id="cartItemsContainer" style="display: flex; flex-direction: column; gap: 20px;"></div>

            <!-- Notes -->
            <div class="note-container">
                <div class="note-header">
                    <span>Notas especiales</span>
                    <span id="charCount">0 / 300</span>
                </div>
                <textarea id="noteTextarea" class="note-textarea" placeholder="Instrucciones adicionales para el pedido..." maxlength="300"></textarea>
            </div>
        </div>
        <div class="panel-footer">
            <button class="btn-send-order" id="btnSendOrder">
                <span id="btnSendText">Confirmar y Enviar</span>
                <div id="btnSendSpinner" class="spinner" style="display:none; width:20px; height:20px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
            </button>
        </div>
    </div>

    <!-- Alert Modal (Success / Failure) -->
    <div class="alert-modal" id="alertModal">
        <div class="alert-card">
            <div class="alert-icon" id="alertIcon">✓</div>
            <h3 class="alert-title" id="alertTitle">Estado</h3>
            <p class="alert-desc" id="alertDesc">Descripción del resultado.</p>
            <button class="btn-alert-close" id="btnAlertClose">Entendido</button>
        </div>
    </div>

    <script>
        // Core Application State
        let catalog = [];
        const cart = {}; // maps codigo -> cantidad
        let currentCategory = "Todos";
        let searchQuery = "";
        let requestId = crypto.randomUUID(); // Idempotency UUID
        
        // Timer de Inactividad (15 minutos = 900,000 ms)
        let inactivityTimeout;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimeout);
            inactivityTimeout = setTimeout(() => {
                window.location.href = 'logout.php';
            }, 900000); 
        }

        // Reset timer on user interactions
        ['mousedown', 'touchstart', 'keydown', 'scroll'].forEach(evt => {
            window.addEventListener(evt, resetInactivityTimer, { passive: true });
        });
        resetInactivityTimer();

        // Elements
        const catalogContainer = document.getElementById('catalogContainer');
        const categoriesContainer = document.getElementById('categoriesContainer');
        const searchInput = document.getElementById('searchInput');
        const cartBar = document.getElementById('cartBar');
        const cartCount = document.getElementById('cartCount');
        const panelOverlay = document.getElementById('panelOverlay');
        const cartPanel = document.getElementById('cartPanel');
        const btnClosePanel = document.getElementById('btnClosePanel');
        const cartItemsContainer = document.getElementById('cartItemsContainer');
        const noteTextarea = document.getElementById('noteTextarea');
        const charCount = document.getElementById('charCount');
        const btnSendOrder = document.getElementById('btnSendOrder');
        const btnSendText = document.getElementById('btnSendText');
        const btnSendSpinner = document.getElementById('btnSendSpinner');
        const alertModal = document.getElementById('alertModal');
        const alertIcon = document.getElementById('alertIcon');
        const alertTitle = document.getElementById('alertTitle');
        const alertDesc = document.getElementById('alertDesc');
        const btnAlertClose = document.getElementById('btnAlertClose');

        // Fetch Catalog from proxy
        async function fetchCatalog() {
            try {
                const response = await fetch('api.php?action=catalogo');
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        return;
                    }
                    throw new Error('Error al cargar catálogo de insumos');
                }
                const data = await response.json();
                catalog = data.items || [];
                
                renderCategories();
                renderCatalog();
            } catch (err) {
                catalogContainer.innerHTML = `
                    <div class="empty-catalog" style="color: var(--danger)">
                        <p style="font-weight: 600; margin-bottom: 12px;">No se pudo conectar con el catálogo.</p>
                        <button onclick="fetchCatalog()" class="category-chip" style="background-color: var(--input-bg); border-color: var(--input-border); color: var(--text-color);">Reintentar</button>
                    </div>
                `;
            }
        }

        // Render category filters
        function renderCategories() {
            const categories = new Set(["Todos"]);
            catalog.forEach(item => {
                if (item.categoria) categories.add(item.categoria);
            });

            categoriesContainer.innerHTML = Array.from(categories).map(cat => {
                const isActive = cat === currentCategory ? 'active' : '';
                return `<div class="category-chip ${isActive}" data-category="${cat}">${cat}</div>`;
            }).join('');

            // Add events
            document.querySelectorAll('.category-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    currentCategory = chip.getAttribute('data-category');
                    renderCatalog();
                });
            });
        }

        // Filter and Render products list
        function renderCatalog() {
            const filtered = catalog.filter(item => {
                const matchesSearch = item.nombre.toLowerCase().includes(searchQuery.toLowerCase());
                const matchesCategory = currentCategory === "Todos" || item.categoria === currentCategory;
                return matchesSearch && matchesCategory;
            });

            if (filtered.length === 0) {
                catalogContainer.innerHTML = `<div class="empty-catalog"><p>No se encontraron insumos.</p></div>`;
                return;
            }

            catalogContainer.innerHTML = filtered.map(item => {
                const valueInCart = cart[item.codigo] || 0;
                const isSelectedClass = valueInCart > 0 ? 'has-items' : '';
                
                return `
                    <div class="product-card ${isSelectedClass}" id="card-${item.codigo}">
                        <div class="product-info">
                            <span class="product-tag">${item.categoria || 'Otros'}</span>
                            <h3 class="product-name">${item.nombre}</h3>
                            <div class="product-meta">
                                <span>Proveedor: ${item.proveedor || 'Sin definir'}</span>
                                <div class="product-meta-dot"></div>
                                <span>Unidad: ${item.unidad || 'und'}</span>
                            </div>
                        </div>
                        <div class="controls-container">
                            <span class="unit-badge">${item.unidad || 'und'}</span>
                            <div class="count-controls">
                                <button class="btn-ctrl" onclick="adjustCount('${item.codigo}', -1, '${item.unidad}')">-</button>
                                <input type="number" class="count-value" id="val-${item.codigo}" 
                                       value="${valueInCart}" step="${getIncrementStep(item.unidad)}" min="0" readonly>
                                <button class="btn-ctrl" onclick="adjustCount('${item.codigo}', 1, '${item.unidad}')">+</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Determine step count based on units
        function getIncrementStep(unidad) {
            const lowUnit = (unidad || '').toLowerCase();
            return (lowUnit === 'kg' || lowUnit === 'kilo' || lowUnit === 'litro' || lowUnit === 'l' || lowUnit === 'kilos') ? 0.5 : 1;
        }

        // Adjust selected quantity
        window.adjustCount = function(codigo, direction, unidad) {
            const step = getIncrementStep(unidad);
            const currentVal = cart[codigo] || 0;
            let newVal = currentVal + (direction * step);
            
            // Decimal math correction
            newVal = Math.round(newVal * 10) / 10;

            const card = document.getElementById(`card-${codigo}`);

            if (newVal <= 0) {
                delete cart[codigo];
                newVal = 0;
                if (card) card.classList.remove('has-items');
            } else {
                cart[codigo] = newVal;
                if (card) card.classList.add('has-items');
            }

            const input = document.getElementById(`val-${codigo}`);
            if (input) input.value = newVal;

            updateCartBar();
        }

        // Update bottom action bar visibility and item counter
        function updateCartBar() {
            const totalItems = Object.keys(cart).length;
            if (totalItems > 0) {
                cartCount.textContent = totalItems;
                cartBar.classList.add('visible');
            } else {
                cartBar.classList.remove('visible');
            }
        }

        // Group items in cart by provider
        function renderCartItems() {
            const groups = {};
            
            // Build groups
            Object.entries(cart).forEach(([codigo, cantidad]) => {
                const prod = catalog.find(item => item.codigo === codigo);
                if (prod) {
                    const provider = prod.proveedor || "Distribuidor General";
                    if (!groups[provider]) groups[provider] = [];
                    groups[provider].push({ ...prod, cantidad });
                }
            });

            if (Object.keys(groups).length === 0) {
                cartItemsContainer.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 20px;">No hay insumos seleccionados.</div>`;
                return;
            }

            cartItemsContainer.innerHTML = Object.entries(groups).map(([provider, items]) => {
                const itemRows = items.map(item => `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <h4 class="cart-item-name">${item.nombre}</h4>
                            <span class="cart-item-unit">${item.codigo} <span style="color: #44403c">•</span> ${item.cantidad} ${item.unidad}</span>
                        </div>
                        <div class="cart-item-actions">
                            <button class="btn-delete-item" onclick="deleteCartItem('${item.codigo}')">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </button>
                        </div>
                    </div>
                `).join('');

                return `
                    <div class="provider-group">
                        <h3 class="provider-title">${provider}</h3>
                        ${itemRows}
                    </div>
                `;
            }).join('');
        }

        // Delete item from Cart directly in review panel
        window.deleteCartItem = function(codigo) {
            delete cart[codigo];
            
            // Sync with catalog UI cards
            const card = document.getElementById(`card-${codigo}`);
            if (card) card.classList.remove('has-items');
            
            const input = document.getElementById(`val-${codigo}`);
            if (input) input.value = 0;

            renderCartItems();
            updateCartBar();

            // If empty, close panel
            if (Object.keys(cart).length === 0) {
                closePanel();
            }
        }

        // Panel Actions
        function openPanel() {
            renderCartItems();
            panelOverlay.classList.add('active');
            cartPanel.classList.add('active');
            document.body.style.overflow = 'hidden'; // block main page scrolling
        }

        function closePanel() {
            panelOverlay.classList.remove('active');
            cartPanel.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Alert Modals
        function showAlert(isSuccess, title, msg) {
            alertIcon.className = 'alert-icon ' + (isSuccess ? 'success' : 'error');
            alertIcon.textContent = isSuccess ? '✓' : '⚠';
            alertTitle.textContent = title;
            alertDesc.textContent = msg;
            alertModal.classList.add('active');
        }

        function closeAlert() {
            alertModal.classList.remove('active');
        }

        // Submit Order
        async function submitOrder() {
            const itemsArray = Object.entries(cart).map(([codigo, cantidad]) => ({
                codigo,
                cantidad
            }));

            if (itemsArray.length === 0) return;

            const nota = noteTextarea.value;

            // UI loading state
            btnSendOrder.disabled = true;
            btnSendText.style.display = 'none';
            btnSendSpinner.style.display = 'block';

            try {
                const response = await fetch('api.php?action=enviar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        nota: nota,
                        items: itemsArray
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    if (data.duplicado) {
                        showAlert(true, 'Pedido ya recibido', `El pedido folio ${data.folio} ya había sido procesado previamente.`);
                    } else {
                        showAlert(true, 'Pedido Enviado', `Pedido registrado con éxito. Folio: ${data.folio}.`);
                    }
                    
                    // Reset cart and generate new requestId on success
                    Object.keys(cart).forEach(k => delete cart[k]);
                    noteTextarea.value = '';
                    charCount.textContent = '0 / 300';
                    requestId = crypto.randomUUID(); 
                    
                    updateCartBar();
                    renderCatalog();
                    closePanel();
                } else {
                    throw new Error(data.error || 'Ocurrió un error en el servidor central.');
                }
            } catch (err) {
                showAlert(false, 'Error de conexión', `${err.message}. Por favor, vuelve a intentarlo.`);
            } finally {
                btnSendOrder.disabled = false;
                btnSendText.style.display = 'block';
                btnSendSpinner.style.display = 'none';
            }
        }

        // Event Listeners
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            renderCatalog();
        });

        cartBar.addEventListener('click', openPanel);
        panelOverlay.addEventListener('click', closePanel);
        btnClosePanel.addEventListener('click', closePanel);
        btnAlertClose.addEventListener('click', closeAlert);
        btnSendOrder.addEventListener('click', submitOrder);

        noteTextarea.addEventListener('input', (e) => {
            const count = e.target.value.length;
            charCount.textContent = `${count} / 300`;
        });

        // Initialize App
        fetchCatalog();
    </script>
</body>
</html>
