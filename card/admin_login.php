<?php
require_once __DIR__ . '/../functions.php';
require_once 'function.php';
sb_session_start_secure();

$cardBuilder = new CardBuilderFunctions();

// CSRF token for subsequent admin actions (login itself is not CSRF-protected here)
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$step = 1;
$error = '';
$success = '';
$username = '';

// Simple Secret Key for Admin Creation
$ADMIN_SECRET_KEY = getenv('CARD_ADMIN_SECRET_KEY');
$ADMIN_SECRET_KEY = ($ADMIN_SECRET_KEY !== false && trim($ADMIN_SECRET_KEY) !== '') ? trim($ADMIN_SECRET_KEY) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- LOGIN FLOW ---
    if ($action === 'verify_user') {
        $u = $_POST['username'] ?? '';
        $p = $_POST['password'] ?? '';
        
        $user = $cardBuilder->authenticateUser($u, $p);
        
        if ($user && $user['role'] === 'admin') {
            $otp = $cardBuilder->generateOTP($u);
            $_SESSION['temp_admin_user'] = $u;
            $step = 2; 
            $username = $u;
        } else {
            $error = 'Invalid admin credentials';
        }
    }
    elseif ($action === 'verify_otp') {
        $otp = $_POST['otp'] ?? '';
        $u = $_SESSION['temp_admin_user'] ?? '';
        
        if ($cardBuilder->verifyOTP($u, $otp)) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $u;
            $_SESSION['role'] = 'admin';
            unset($_SESSION['temp_admin_user']);
            header('Location: admin.php');
            exit();
        } else {
             $error = 'Invalid or Expired OTP';
             $step = 2;
             $username = $u;
        }
    }
    elseif ($action === 'resend_otp') {
        $u = $_SESSION['temp_admin_user'] ?? '';
        if($u) {
            $cardBuilder->generateOTP($u);
            $error = 'New OTP sent to otp.json'; 
            $step = 2;
            $username = $u;
        } else {
            $step = 1;
        }
    }
    
    // --- REGISTER FLOW ---
    elseif ($action === 'register_admin') {
        $u = $_POST['reg_username'] ?? '';
        $p = $_POST['reg_password'] ?? '';
        $key = $_POST['secret_key'] ?? '';
        $email = $_POST['reg_email'] ?? 'admin@local.host'; // Optional
        
        if (!$ADMIN_SECRET_KEY) {
            $error = 'Admin registration disabled. Set CARD_ADMIN_SECRET_KEY in the server environment.';
        }
        elseif (hash_equals($ADMIN_SECRET_KEY, (string)$key)) {
            // Create user with 'admin' role
            if ($cardBuilder->createUser($u, $p, $email, 'Admin User', 'admin')) {
                $success = 'Admin account created! Please login.';
                $step = 1;
            } else {
                $error = 'Username already exists.';
            }
        } else {
            $error = 'Invalid Secret Key. Access Denied.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Secure Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --input-bg: rgba(255, 255, 255, 0.1);
            --border-color: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --error-bg: rgba(239, 68, 68, 0.2);
            --error-text: #fca5a5;
            --success-bg: rgba(34, 197, 94, 0.2);
            --success-text: #86efac;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow: hidden;
        }

        .glow {
            position: absolute; width: 600px; height: 600px; background: var(--accent);
            opacity: 0.15; filter: blur(100px); border-radius: 50%; z-index: -1;
            animation: pulse 8s ease-in-out infinite alternate;
        }
        @keyframes pulse { 0% { transform: scale(1); opacity: 0.1; } 100% { transform: scale(1.2); opacity: 0.2; } }

        .login-card {
            width: 100%; max-width: 400px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            margin: 20px;
            position: relative;
            overflow: hidden;
            transition: height 0.3s ease;
        }

        .login-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        }

        h2 { font-size: 28px; font-weight: 600; text-align: center; margin-bottom: 8px; letter-spacing: -0.5px; }
        p.subtitle { text-align: center; color: var(--text-muted); font-size: 14px; margin-bottom: 32px; }

        .form-group { margin-bottom: 20px; position: relative; }
        
        label { 
            position: absolute; left: 16px; top: 16px; font-size: 14px; color: var(--text-muted);
            transition: all 0.2s; pointer-events: none;
        }

        input {
            width: 100%; padding: 24px 16px 8px; 
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: white; font-size: 16px; font-family: inherit;
            transition: all 0.2s;
        }
        
        input:focus, input:not(:placeholder-shown) { outline: none; border-color: var(--accent); background: rgba(255,255,255,0.15); }
        input:focus ~ label, input:not(:placeholder-shown) ~ label { transform: translateY(-10px); font-size: 11px; color: var(--accent); }

        button.btn-primary {
            width: 100%; padding: 16px;
            background: var(--accent);
            color: white; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 500;
            cursor: pointer; transition: all 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
        }
        button.btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4); }

        .alert-box {
            padding: 12px; border-radius: 12px;
            font-size: 13px; text-align: center;
            margin-bottom: 24px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            animation: shake 0.4s ease-in-out;
        }
        .error { background: var(--error-bg); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--error-text); }
        .success { background: var(--success-bg); border: 1px solid rgba(34, 197, 94, 0.3); color: var(--success-text); }
        
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }

        .back-link { display: block; text-align: center; margin-top: 24px; font-size: 13px; color: var(--text-muted); text-decoration: none; opacity: 0.7; transition: opacity 0.2s; }
        .back-link:hover { opacity: 1; }

        .otp-info {
            background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2);
            padding: 16px; border-radius: 12px; margin-bottom: 24px;
            text-align: center; font-size: 13px; color: #a5b4fc;
        }
        .otp-info code { background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        
        .resend-link {
            background: none; border: none; color: var(--text-muted); font-size: 12px; 
            cursor: pointer; text-decoration: underline; margin-top: 12px; display: block; margin-inline: auto;
        }
        
        .toggle-link {
            text-align: center; margin-top: 15px; font-size: 13px; color: var(--text-muted); cursor: pointer;
        }
        .toggle-link span { color: var(--accent); font-weight: 500; }
        
        /* View Switching */
        .view-section { display: none; }
        .view-section.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .lock-icon { font-size: 40px; text-align: center; margin-bottom: 20px; display: block; }
    </style>
</head>
<body>
    <div class="glow"></div>

    <div class="login-card">
        <div class="lock-icon">🔐</div>
        <h2>Admin Portal</h2>
        
        <?php if($error): ?>
            <div class="alert-box error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert-box success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if($step === 1): ?>
            <!-- LOGIN VIEW -->
            <div id="view-login" class="view-section active">
                <p class="subtitle">Authenticate to continue</p>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_user">
                    <div class="form-group">
                        <input type="text" name="username" id="username" placeholder=" " required autofocus autocomplete="off">
                        <label for="username">Username</label>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" id="password" placeholder=" " required>
                        <label for="password">Password</label>
                    </div>
                    <button class="btn-primary">Verify Credentials</button>
                    
                    <div class="toggle-link" onclick="switchView('register')">
                        New Admin? <span>Create Account</span>
                    </div>
                </form>
            </div>

            <!-- REGISTER VIEW -->
            <div id="view-register" class="view-section">
                <p class="subtitle">Create New Admin Account</p>
                <form method="POST">
                    <input type="hidden" name="action" value="register_admin">
                    <div class="form-group">
                        <input type="text" name="reg_username" id="reg_username" placeholder=" " required autocomplete="off">
                        <label for="reg_username">New Username</label>
                    </div>
                    <div class="form-group">
                        <input type="password" name="reg_password" id="reg_password" placeholder=" " required>
                        <label for="reg_password">New Password</label>
                    </div>
                    <div class="form-group">
                        <input type="password" name="secret_key" id="secret_key" placeholder=" " required>
                        <label for="secret_key">Secret Key</label>
                    </div>
                    <button class="btn-primary">Create Admin</button>
                    
                    <div class="toggle-link" onclick="switchView('login')">
                        Has Account? <span>Login Here</span>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- OTP VIEW -->
            <div class="view-section active">
                <p class="subtitle">Two-Factor Verification</p>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="otp-info">
                        OTP Code sent to secure log.<br>
                        Open <code>otp.json</code> to view code.
                    </div>
                    <div class="form-group">
                        <input type="text" name="otp" id="otp" placeholder=" " required autofocus maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 20px; font-weight: 600;">
                        <label for="otp" style="left: 50%; transform: translateX(-50%);">Enter 6-Digit Code</label>
                    </div>
                    <button class="btn-primary">Unlock Dashboard</button>
                </form>
                <form method="POST" style="margin-top:10px;">
                    <input type="hidden" name="action" value="resend_otp">
                    <button class="resend-link">Resend Verification Code</button>
                </form>
            </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">Return to Application</a>
    </div>

    <script>
        function switchView(view) {
            document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
            document.getElementById('view-' + view).classList.add('active');
        }
    </script>
</body>
</html>
