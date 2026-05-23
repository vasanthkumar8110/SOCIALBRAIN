<?php
require_once __DIR__ . '/../functions.php';
require_once 'function.php';
sb_session_start_secure();

// Generate Math Captcha
if (empty($_SESSION['captcha'])) {
    $n1 = rand(1, 9);
    $n2 = rand(1, 9);
    $_SESSION['captcha'] = $n1 + $n2;
    $_SESSION['captcha_q'] = "$n1 + $n2 = ?";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login - Card Builder</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { background: white; width: 400px; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; color: #666; font-weight: 500; }
        .tab.active { border-bottom: 2px solid #2563eb; color: #2563eb; }
        
        .form-view { display: none; }
        .form-view.active { display: block; }

        input, select { width: 100%; padding: 10px; margin: 8px 0 12px; border: 1px solid #ccc; border-radius: 6px; }
        button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        button:hover { background: #1d4ed8; }

        .pass-strength { height: 4px; background: #eee; margin-bottom: 12px; border-radius: 2px; overflow: hidden; }
        .pass-strength div { height: 100%; width: 0%; transition: width 0.3s, background 0.3s; }
        
        .toast { position: fixed; top: 20px; right: 20px; background: #333; color: white; padding: 10px 20px; border-radius: 6px; display: none; }
    </style>
</head>
<body>

    <div class="container">
        <div class="tabs">
            <div class="tab active" onclick="switchTab('login')">Login</div>
            <div class="tab" onclick="switchTab('register')">Register</div>
            <div class="tab" onclick="switchTab('forgot')">Recover</div>
        </div>

        <!-- LOGIN -->
        <form id="form-login" class="form-view active" onsubmit="handleLogin(event)">
            <h3>Welcome Back</h3>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button>Login</button>
        </form>

        <!-- REGISTER -->
        <form id="form-register" class="form-view" onsubmit="handleRegister(event)">
            <h3>Create Account</h3>
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            
            <input type="password" id="reg-pass" name="password" placeholder="Strong Password" oninput="checkStrength(this.value)" required>
            <div class="pass-strength"><div id="strength-bar"></div></div>
            <small id="strength-text" style="display:block; margin-bottom:10px; font-size:11px; color:#666;">Min 8 chars, 1 Uppercase, 1 Number.</small>

            <select name="question" required>
                <option value="">Select Security Question</option>
                <option value="pet">What was your first pet's name?</option>
                <option value="school">What was your primary school?</option>
                <option value="city">In which city were you born?</option>
            </select>
            <input type="text" name="answer" placeholder="Security Answer" required>

            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <span style="font-weight:bold; color:#d97706;"><?= $_SESSION['captcha_q'] ?></span>
                <input type="number" name="captcha" placeholder="Answer" style="margin:0; width:80px;" required>
            </div>

            <button>Register Securely</button>
        </form>

        <!-- FORGOT PASS -->
        <div id="form-forgot" class="form-view">
            <div id="forgot-step-1">
                <h3>Account Recovery</h3>
                <input type="text" id="rec-user" placeholder="Enter your username">
                <button onclick="checkUser()">Next</button>
            </div>
            
            <div id="forgot-step-2" style="display:none;">
                <h3 id="sec-q-display">Security Question?</h3>
                <input type="text" id="rec-answer" placeholder="Your Answer">
                <input type="password" id="rec-new-pass" placeholder="New Password">
                <button onclick="resetPass()">Reset Password</button>
            </div>
        </div>

    </div>
    
    <div class="toast" id="toast"></div>

    <script>
        const API = 'function.php';
        const CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-view').forEach(f => f.classList.remove('active'));
            
            document.querySelector(`.tab[onclick="switchTab('${tab}')"]`).classList.add('active');
            document.getElementById(`form-${tab}`).classList.add('active');
        }

        // --- Logic ---
        async function handleLogin(e) {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.append('action', 'login');
            
            const res = await callApi(fd);
            if(res.success) window.location.href = res.data.redirect;
            else showToast(res.message);
        }

        async function handleRegister(e) {
            e.preventDefault();
            const pass = document.getElementById('reg-pass').value;
            if(!isStrong(pass)) {
                showToast('Password is too weak!');
                return;
            }

            const fd = new FormData(e.target);
            fd.append('action', 'register_secure');
            
            const res = await callApi(fd);
            if(res.success) {
                showToast('Registration successful! Please login.');
                switchTab('login');
                e.target.reset();
            } else {
                showToast(res.message);
            }
        }

        // Forgot Password Flow
        async function checkUser() {
            const user = document.getElementById('rec-user').value;
            if(!user) return;

            const fd = new FormData();
            fd.append('action', 'get_security_question');
            fd.append('username', user);
            
            const res = await callApi(fd);
            if(res.success) {
                document.getElementById('sec-q-display').textContent = res.data.question;
                document.getElementById('forgot-step-1').style.display = 'none';
                document.getElementById('forgot-step-2').style.display = 'block';
            } else {
                showToast(res.message);
            }
        }

        async function resetPass() {
            const user = document.getElementById('rec-user').value;
            const ans = document.getElementById('rec-answer').value;
            const newP = document.getElementById('rec-new-pass').value;
            
            if(!isStrong(newP)) { showToast('New password is too weak'); return; }

            const fd = new FormData();
            fd.append('action', 'reset_password');
            fd.append('username', user);
            fd.append('answer', ans);
            fd.append('new_password', newP);

            const res = await callApi(fd);
            if(res.success) {
                showToast('Password Reset! Login now.');
                switchTab('login');
            } else {
                showToast(res.message);
            }
        }

        // Utils
        async function callApi(fd) {
            try {
                const headers = CSRF ? { 'X-CSRF-Token': CSRF } : {};
                const r = await fetch(API, {method:'POST', body:fd, headers});
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch(e) {
                    console.error('Server Error:', text);
                    return {success:false, message:'Server Error. Check console.'};
                }
            } catch(e) { return {success:false, message:'Network error'}; }
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 3000);
        }

        function checkStrength(val) {
            const bar = document.getElementById('strength-bar');
            let score = 0;
            if(val.length >= 8) score++;
            if(/[A-Z]/.test(val)) score++;
            if(/[0-9]/.test(val)) score++;
            
            const cols = ['red', 'orange', 'green'];
            const w = ['33%', '66%', '100%'];
            
            bar.style.width = score > 0 ? w[score-1] : '0%';
            bar.style.backgroundColor = score > 0 ? cols[score-1] : 'transparent';
        }

        function isStrong(val) {
            return val.length >= 8 && /[A-Z]/.test(val) && /[0-9]/.test(val);
        }
    </script>
</body>
</html>
