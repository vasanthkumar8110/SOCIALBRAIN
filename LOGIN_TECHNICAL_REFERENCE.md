# Technical Implementation Guide - Login Landing Page

## Quick Reference for Developers

### Core JavaScript Functions

#### 1. CSRF Token Management
```javascript
let csrfToken = null;

// Initialize CSRF token on page load
async function initializeCSRF() {
    try {
        const response = await fetch('index.php?action=get_csrf', {
            credentials: 'include'
        });
        const data = await response.json();
        csrfToken = data.csrf_token || null;
    } catch (error) {
        showAlert('main-panel', 'Error fetching CSRF token', 'error');
    }
}

// Usage: Add to every POST request
headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken
}
```

#### 2. Tab Navigation
```javascript
function switchTab(tab) {
    // Hide all panels
    document.getElementById('landing-panel').style.display = 'none';
    document.getElementById('login-panel').style.display = 'none';
    document.getElementById('register-panel').style.display = 'none';
    document.getElementById('recover-panel').style.display = 'none';

    // Remove active class
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('tab-btn-active');
    });

    // Show selected panel
    document.getElementById(`${tab}-panel`).style.display = 'flex';
    event.target.classList.add('tab-btn-active');
}
```

#### 3. Password Validation
```javascript
function checkPasswordStrength(fieldId) {
    const field = document.getElementById(fieldId);
    const password = field.value;
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    // Check requirements
    const hasLength = password.length >= 10;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

    // Calculate strength
    let strength = 0;
    if (hasLength) strength++;
    if (hasUpper) strength++;
    if (hasLower) strength++;
    if (hasNumber) strength++;
    if (hasSpecial) strength++;

    // Visual feedback
    if (strength <= 2) {
        strengthBar.style.width = '33%';
        strengthBar.style.backgroundColor = '#ef4444'; // Red
        strengthText.textContent = 'Weak';
    } else if (strength <= 3) {
        strengthBar.style.width = '66%';
        strengthBar.style.backgroundColor = '#f59e0b'; // Amber
        strengthText.textContent = 'Fair';
    } else {
        strengthBar.style.width = '100%';
        strengthBar.style.backgroundColor = '#10b981'; // Green
        strengthText.textContent = 'Good';
    }
}
```

#### 4. Form Submission with Security
```javascript
async function handleLogin(event) {
    event.preventDefault();

    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;

    // Client-side validation
    if (username.length < 3) {
        showAlert('login-panel', 'Username must be at least 3 characters', 'error');
        return;
    }
    if (password.length < 10) {
        showAlert('login-panel', 'Password must be at least 10 characters', 'error');
        return;
    }

    // Show loading state
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    try {
        // Ensure CSRF token exists
        if (!csrfToken) await initializeCSRF();

        // Make authenticated request
        const response = await fetch('index.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include', // Send cookies
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
            showAlert('login-panel', 'Login successful!', 'success');
            setTimeout(() => {
                window.location.href = data.data?.redirect || 'index.php';
            }, 1500);
        } else {
            showAlert('login-panel', data.message || 'Login failed', 'error');
        }
    } catch (error) {
        showAlert('login-panel', 'Error during login: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}
```

#### 5. Input Sanitization (XSS Prevention)
```javascript
function sanitizeInput(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

// Usage in forms
const username = sanitizeInput(rawInput);
```

#### 6. Password Recovery Multi-Step
```javascript
// Step 1: Get security question
async function handleRecoverStep1(event) {
    event.preventDefault();
    const username = document.getElementById('recover-username').value.trim();

    if (username.length < 3) {
        showAlert('recover-panel', 'Invalid username', 'error');
        return;
    }

    try {
        const response = await fetch('index.php?action=recover_password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({
                step: 1,
                username: sanitizeInput(username)
            })
        });

        const data = await response.json();

        if (data.success) {
            recoveryState = { username, step: 2 };
            document.getElementById('recover-step-1').style.display = 'none';
            document.getElementById('recover-step-2').style.display = 'block';
            document.getElementById('security-question').textContent = data.question;
        } else {
            showAlert('recover-panel', data.message || 'User not found', 'error');
        }
    } catch (error) {
        showAlert('recover-panel', 'Error: ' + error.message, 'error');
    }
}

// Step 2: Verify security answer
async function handleRecoverStep2(event) {
    event.preventDefault();
    const answer = document.getElementById('security-answer').value.trim();

    try {
        const response = await fetch('index.php?action=recover_password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({
                step: 2,
                username: recoveryState.username,
                answer: sanitizeInput(answer)
            })
        });

        const data = await response.json();

        if (data.success) {
            recoveryState.step = 3;
            document.getElementById('recover-step-2').style.display = 'none';
            document.getElementById('recover-step-3').style.display = 'block';
        } else {
            showAlert('recover-panel', 'Incorrect security answer', 'error');
        }
    } catch (error) {
        showAlert('recover-panel', 'Error: ' + error.message, 'error');
    }
}

// Step 3: Reset password
async function handleRecoverStep3(event) {
    event.preventDefault();
    const password = document.getElementById('recover-new-password').value;
    const confirmPassword = document.getElementById('recover-confirm-password').value;

    if (password.length < 10) {
        showAlert('recover-panel', 'Password must be at least 10 characters', 'error');
        return;
    }
    if (password !== confirmPassword) {
        showAlert('recover-panel', 'Passwords do not match', 'error');
        return;
    }

    try {
        const response = await fetch('index.php?action=recover_password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({
                step: 3,
                username: recoveryState.username,
                password: password // Backend hashes with bcrypt
            })
        });

        const data = await response.json();

        if (data.success) {
            showAlert('recover-panel', 'Password reset successful! Redirecting...', 'success');
            setTimeout(() => {
                recoveryState = null;
                switchTab('login');
            }, 2000);
        } else {
            showAlert('recover-panel', data.message || 'Reset failed', 'error');
        }
    } catch (error) {
        showAlert('recover-panel', 'Error: ' + error.message, 'error');
    }
}
```

---

## API Integration Points

### 1. GET /index.php?action=get_csrf

**Purpose:** Retrieve CSRF token for new session

**Response:**
```json
{
    "csrf_token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
}
```

**Called by:** `initializeCSRF()`

---

### 2. POST /index.php?action=login

**Request:**
```json
{
    "username": "admin",
    "password": "SecurePassword123!",
    "remember": true
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "redirect": "index.php"
    },
    "message": "Logged in successfully"
}
```

**Response (Error):**
```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

---

### 3. POST /index.php?action=register_invited

**Request:**
```json
{
    "token": "invite_token_from_admin",
    "username": "newuser",
    "password": "StrongPassword123!"
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "redirect": "index.php"
    },
    "message": "Account created successfully"
}
```

**Response (Error):**
```json
{
    "success": false,
    "message": "Invite token expired or invalid"
}
```

---

### 4. POST /index.php?action=recover_password

**Step 1 Request:**
```json
{
    "step": 1,
    "username": "admin"
}
```

**Step 1 Response:**
```json
{
    "success": true,
    "question": "What is your pet's name?"
}
```

**Step 2 Request:**
```json
{
    "step": 2,
    "username": "admin",
    "answer": "Fluffy"
}
```

**Step 2 Response:**
```json
{
    "success": true,
    "message": "Answer verified, proceed to reset"
}
```

**Step 3 Request:**
```json
{
    "step": 3,
    "username": "admin",
    "password": "NewPassword123!"
}
```

**Step 3 Response:**
```json
{
    "success": true,
    "message": "Password reset successful"
}
```

---

## CSS Classes Reference

### Layout Classes
- `.container` - Main wrapper (max 450px)
- `.panel` - Form panels
- `.tab-nav` - Tab navigation
- `.form-group` - Input wrapper

### Interactive Classes
- `.tab-btn` - Tab button (inactive)
- `.tab-btn-active` - Tab button (active)
- `.btn-submit` - Primary action button
- `.btn-secondary` - Secondary action button

### State Classes
- `.hidden` - Display none
- `.disabled` - Disabled state
- `.focus-visible` - Keyboard focus indicator

### Alert Classes
- `.alert` - Alert wrapper
- `.alert-error` - Error styling (red)
- `.alert-success` - Success styling (green)
- `.alert-warning` - Warning styling (amber)

---

## Security Headers

All fetch requests include:
```javascript
headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken,
    'Cache-Control': 'no-store'
}
```

All requests sent with:
```javascript
credentials: 'include'  // Send session cookies
```

---

## Session Security

Backend enforces (in index.php):
```php
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// Session cookies set with:
// - HttpOnly (prevent JS access)
// - Secure (HTTPS only)
// - SameSite=Lax (CSRF reduction)
```

---

## Event Listeners

**DOMContentLoaded:**
- Initialize CSRF token
- Check for invite token in URL
- Pre-fill register form if invite present
- Set up tab click handlers

**Page Unload:**
- Clear all password fields
- Clear session variables
- Remove event listeners

**Form Submit:**
- Validate input
- Add CSRF header
- Show loading state
- Handle response

---

## Testing Checklist

### Unit Tests
- [ ] Password strength validation logic
- [ ] Input sanitization for XSS
- [ ] CSRF token caching
- [ ] Tab switching

### Integration Tests
- [ ] Login flow end-to-end
- [ ] Registration with invite flow
- [ ] Password recovery all 3 steps
- [ ] CSRF token rejection handling

### Security Tests
- [ ] CSRF without token fails
- [ ] XSS injection blocked
- [ ] SQLi in username rejected
- [ ] Weak passwords rejected
- [ ] Sessions expire correctly

### UX Tests
- [ ] All forms accessible via Tab
- [ ] Mobile layout responsive
- [ ] Error messages clear
- [ ] Loading states visible
- [ ] Alerts disappear after 3s

---

## Common Issues & Solutions

### Issue: CSRF token undefined
**Solution:** Call `initializeCSRF()` on page load, store result in `csrfToken` variable

### Issue: Password not hashing on backend
**Solution:** Verify `password_hash(password, PASSWORD_DEFAULT)` in index.php

### Issue: Session not persisting
**Solution:** Check `credentials: 'include'` in fetch calls and `HttpOnly` flag in backend

### Issue: Invite token not pre-filled
**Solution:** URL must be `?invite=TOKEN`, check `URLSearchParams` parsing

### Issue: Mobile buttons too small
**Solution:** Verify min-height 44px on buttons per WCAG guidelines

---

## Browser DevTools Debugging

### Check CSRF Token
```javascript
// In console
console.log(csrfToken);
```

### Check Session Cookie
1. DevTools → Application → Cookies
2. Look for `PHPSESSID` with HttpOnly, Secure, SameSite=Lax flags

### Monitor API Calls
1. DevTools → Network tab
2. Filter by `Fetch/XHR`
3. Check request headers include `X-CSRF-Token`
4. Verify response JSON

### Performance Debug
```javascript
// Add to functions for timing
console.time('login');
// ... function code
console.timeEnd('login');
```

---

## Version History

- **2.0** - Complete redesign with CSRF, password validation, recovery flow
- **1.0** - Initial portal selection interface

---

## Related Files

- `login_landing.html` - Main implementation (900 lines)
- `login_styles.css` - Glassmorphism/responsive styles (if separate)
- `index.php` - Backend endpoint handlers
- `functions.php` - Security functions (sb_session_start_secure, etc.)
- `admin_config.json` - Configuration and team settings

---

**Document Version:** 1.0  
**Last Updated:** May 6, 2026  
**Status:** Complete  
