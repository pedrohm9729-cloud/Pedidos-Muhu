<?php
require_once __DIR__ . '/config.php';
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS'])),
    'cookie_samesite' => 'Strict',
]);

// If session is already active, redirect to index.php
if (isset($_SESSION['user'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php');
        exit;
    }
}

// Handle POST authentication request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Read JSON payload
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $username = trim($input['username'] ?? $_POST['username'] ?? '');
    $password = $input['password'] ?? $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Usuario y contraseña son requeridos.']);
        exit;
    }
    
    if (isset($PEDIDOS_USERS[$username])) {
        $user_info = $PEDIDOS_USERS[$username];
        if (password_verify($password, $user_info['hash'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            // Resolver rol: explícito en config, o inferido por username.
            $role = !empty($user_info['role'])
                ? $user_info['role']
                : ($username === 'admin' ? 'admin' : 'staff');

            $_SESSION['user'] = [
                'username' => $username,
                'name'     => $user_info['name'],
                'role'     => $role
            ];
            $_SESSION['last_activity'] = time();
            
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales incorrectas.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Iniciar Sesión - Pedidos Muhu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0c0a09;
            --card-bg: #1c1917;
            --text-color: #fafaf9;
            --text-muted: #a8a29e;
            --primary: #d97706; /* Muhu Amber/Gold */
            --primary-hover: #b45309;
            --input-bg: #292524;
            --input-border: #44403c;
            --error-color: #ef4444;
            --success-color: #10b981;
            --font-family: 'Outfit', sans-serif;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            overflow-x: hidden;
        }

        /* Beautiful glowing background blobs */
        .bg-blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(217, 119, 6, 0.15) 0%, transparent 70%);
            z-index: -1;
            filter: blur(40px);
            pointer-events: none;
        }
        .blob-1 { top: 10%; left: 10%; }
        .blob-2 { bottom: 10%; right: 10%; }

        .login-card {
            background-color: var(--card-bg);
            border: 1px solid #2e2a24;
            border-radius: 24px;
            padding: 40px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            text-align: center;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-container {
            margin-bottom: 24px;
        }
        .logo {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        .subtitle {
            font-size: 16px;
            color: var(--text-muted);
            margin-top: 8px;
            margin-bottom: 32px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input {
            width: 100%;
            height: 52px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 0 16px;
            font-family: var(--font-family);
            font-size: 16px;
            color: var(--text-color);
            transition: var(--transition);
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
            background-color: #24201e;
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: var(--font-family);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(217, 119, 6, 0.3);
        }
        .btn-submit:active {
            transform: translateY(1px);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
            text-align: left;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="login-card">
        <div class="logo-container">
            <h1 class="logo">MUHU</h1>
            <p class="subtitle">Pedidos de Insumos</p>
        </div>

        <div id="errorBox" class="error-message"></div>

        <form id="loginForm" autocomplete="on">
            <div class="form-group">
                <label for="username" class="form-label">Usuario</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Ingresa tu usuario" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Ingresa tu contraseña" required>
            </div>

            <button type="submit" id="submitBtn" class="btn-submit">
                <span id="btnText">Entrar</span>
                <div id="btnSpinner" class="spinner"></div>
            </button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            const errorBox = document.getElementById('errorBox');
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'block';
            errorBox.style.display = 'none';
            
            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    window.location.href = 'index.php';
                } else {
                    throw new Error(data.error || 'Credenciales inválidas');
                }
            } catch (err) {
                errorBox.textContent = err.message;
                errorBox.style.display = 'block';
                
                submitBtn.disabled = false;
                btnText.style.display = 'block';
                btnSpinner.style.display = 'none';
            }
        });
    </script>
</body>
</html>
