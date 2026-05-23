# Security Verification Report - SocialBrain

**Date:** May 6, 2026  
**Status:** P0 hardening complete; ready for regression testing

---

## Executive Summary

All P0 security hardening has been implemented across the main app (`index.php`), R&D API (`api_intelligence_rd.php`), and card module (`card/*.php`). 

**Key improvements:**
- ✅ Session cookie security flags enforced (HttpOnly, Secure, SameSite)
- ✅ CSRF protection on all authenticated POST actions
- ✅ Session fixation prevention (regenerate_id on login)
- ✅ Logout cookie clearing implemented
- ✅ API key header-only (query string removed)
- ✅ Atomic JSON writes with LOCK_EX
- ✅ Rate limiting on R&D API (60 req/min per IP)
- ✅ CORS allowlist configured

---

## P0 Issues - Fixed ✅

### 1. Session Cookie Hardening

**File:** `functions.php::sb_session_start_secure()`  
**Status:** ✅ IMPLEMENTED

**What was done:**
- All entrypoints now call `sb_session_start_secure()` **BEFORE** any content output
- Sets cookie params: `lifetime=0, path=/, secure={https}, httponly=true, samesite=Lax`
- Enforces: `session.use_strict_mode=1`, `session.use_only_cookies=1`
- Generates CSRF token: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`

**Affected files:**
- `index.php` - line 3: `sb_session_start_secure()`
- `api_intelligence_rd.php` - line 3: `sb_session_start_secure()`
- `card/admin_login.php` - line 2: Require parent `functions.php`, call `sb_session_start_secure()`
- `card/admin.php` - line 2: `sb_session_start_secure()`
- `card/index.php` - line 2: `sb_session_start_secure()`
- `card/view.php` - line 2: `sb_session_start_secure()`
- `card/login.php` - line 2: `sb_session_start_secure()`
- `card/logout.php` - line 2: `sb_session_start_secure()`

**Verification (HTTP):**
```bash
# Check Set-Cookie header after login
curl -i http://localhost/index.php
# Expected:
# Set-Cookie: PHPSESSID=...; HttpOnly; Path=/; SameSite=Lax
# (Secure flag only on HTTPS)
```

---

### 2. CSRF Protection

**File:** `functions.php::sb_require_csrf()` + `index.php` router  
**Status:** ✅ IMPLEMENTED

**What was done:**
- Generated CSRF token on every session start
- Main app enforces CSRF on all authenticated POST (except 4 exempt actions: login, signup, recover, register)
- Card module API validates CSRF for all logged-in POST requests
- UI automatically injects `X-CSRF-Token` header (main app)

**Code locations:**
- Main app, lines ~31-40: CSRF validation wrapper
- `card/function.php`, line ~745: CSRF check for authenticated requests
- Card forms in `card/index.php`, `card/admin.php` use `X-CSRF-Token` header

**Example - Main App:**
```php
if ($method === 'POST' && !in_array($action, $csrfExempt, true)) {
    if (!empty($_SESSION['user_id']) && empty($currentUser['is_api'])) {
        sb_require_csrf();  // Enforces X-CSRF-Token or POST csrf_token
    }
}
```

**Example - Card API:**
```php
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
$needCsrf = !empty($_SESSION['user_id']);
if ($needCsrf) {
    if (!hash_equals($sess, $csrfToken)) {
        exit_csrf_fail();  // 403 Forbidden
    }
}
```

**Verification:**
```bash
# Test CSRF rejection
curl -X POST http://localhost/index.php \
  -d 'action=save_post' \
  --cookie "PHPSESSID=..." \
  -H "Content-Type: application/x-www-form-urlencoded"
# Expected: 403 Forbidden + "CSRF validation failed"

# Test with valid token
curl -X POST http://localhost/index.php \
  -d 'action=save_post&csrf_token=' \
  --cookie "PHPSESSID=..." \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <valid_token>"
# Expected: 200 OK (or relevant action response)
```

---

### 3. Session Fixation Prevention

**File:** `index.php` (login handler + magic link), `card/function.php` (login), `card/admin_login.php` (OTP)  
**Status:** ✅ IMPLEMENTED

**What was done:**
- `session_regenerate_id(true)` called on **every** login finalization
- Main app: login handler (line ~64), magic link verification (NEW - line ~110)
- Card: user login (line ~780), admin OTP verification (line ~45)

**Changes made:**
- **index.php, magic link:** Added `session_regenerate_id(true)` before setting `$_SESSION['user_id']`
- **card/admin_login.php, OTP:** Added `session_regenerate_id(true)` before setting admin session

**Verification:**
```bash
# Monitor session ID change
# 1. Pre-login: curl -c cookies.txt http://localhost/index.php
# 2. Login: curl -X POST -b cookies.txt http://localhost/index.php \
#   -d 'action=login&username=admin&password=...' -c cookies.txt
# 3. Extract old and new PHPSESSID, should differ
```

---

### 4. Logout Cookie Clearing

**File:** `index.php` (line 333) + `card/logout.php` (line 2)  
**Status:** ✅ IMPLEMENTED

**What was done:**
```php
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
```

This ensures the PHPSESSID cookie is explicitly cleared from the browser.

**Verification:**
```bash
# Before logout: PHPSESSID present in DevTools
# After logout: PHPSESSID should be expired/removed
curl -v http://localhost/index.php?action=logout
# Look for: Set-Cookie: PHPSESSID=; Expires=<past_date>; ...
```

---

### 5. API Key Header-Only

**File:** `functions.php::checkAuthAndGetRole()`  
**Status:** ✅ IMPLEMENTED

**What was done:**
- Removed: `$_GET['api_key']` fallback
- Kept: `$_SERVER['HTTP_X_API_KEY']` and `Authorization: Bearer <token>` support
- R&D API only accepts `X-API-Key` header or `Authorization` header

**Code (line ~140):**
```php
$apiKeyInput = $_SERVER['HTTP_X_API_KEY'] ?? null;
if (!$apiKeyInput) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (is_string($auth) && stripos($auth, 'Bearer ') === 0) {
        $apiKeyInput = trim(substr($auth, 7));
    }
}
```

**Verification:**
```bash
# ❌ This now FAILS (query string removed)
curl "http://localhost/api_intelligence_rd.php?action=overview&api_key=secret"
# Expected: "Unauthorized"

# ✅ This works (header)
curl -H "X-API-Key: secret" "http://localhost/api_intelligence_rd.php?action=overview"
# Expected: JSON data

# ✅ This also works (Bearer token)
curl -H "Authorization: Bearer secret" "http://localhost/api_intelligence_rd.php?action=overview"
# Expected: JSON data
```

---

### 6. Duplicate Auth Systems Consolidated

**Card Module Hardening**

**Files affected:**
- `card/admin_login.php` - now uses `sb_session_start_secure()` + parent `functions.php`
- `card/admin.php` - hardened session bootstrap
- `card/index.php`, `card/view.php`, `card/login.php`, `card/logout.php` - all hardened
- `card/function.php` - API handler uses `sb_session_start_secure()`

**What changed:**
- All card files now require parent `functions.php` for shared hardened bootstrap
- Card API validates CSRF for authenticated requests
- Card login regenerates session ID
- Card password policy enforced (min 10 chars)
- Atomic JSON writes via `atomicWriteFile()`

---

## P1 Issues - Status

### Rate Limiting (Mostly OK, can improve)

**File:** `api_intelligence_rd.php`  
**Status:** ✅ IMPLEMENTED (with noted limitations)

**What's implemented:**
- 60 requests per minute per IP
- File-based tracking with atomic writes (LOCK_EX)
- Returns 429 Too Many Requests

**Known limitation:** Shared hosting concurrency and botnet bypasses (bypassable via multiple IPs).  
**Recommendation:** Move to Redis/MySQL for production.

**Verification:**
```bash
# Burst 70 requests, should hit limit
for i in {1..70}; do
  curl -H "X-API-Key: test" "http://localhost/api_intelligence_rd.php?action=overview"
done
# ~60 requests succeed, remainder get 429
```

### Password Policy (Server-Side Enforced)

**Status:** ✅ IMPLEMENTED (10-char minimum)

**Files:**
- `index.php` - signup (line 127), admin register (line 294)
- `card/function.php` - register (line 792)

**What's enforced:**
- Minimum 10 characters
- No brute-force lockout yet (P1 recommendation for future)

---

## P2 Issues - Status

### JSON Concurrency (Fixed)

**File:** `functions.php::saveJSON()` + `card/function.php::atomicWriteFile()`  
**Status:** ✅ IMPLEMENTED

**What's done:**
- Main app uses temp file + rename with LOCK_EX
- Card module uses dedicated `atomicWriteFile()` method
- OTP writes are also atomic

---

## Regression Testing Checklist

Run these tests to ensure the app still works correctly:

### Main App
- [ ] **Login success** → new session created, CSRF token present, `session_regenerate_id` occurred
- [ ] **Login failure** → no session role set
- [ ] **Signup** → rejects password < 10 chars
- [ ] **Save post** → requires valid CSRF token (POST without token fails with 403)
- [ ] **Save post** → succeeds with valid token
- [ ] **Logout** → session destroyed, cookie cleared (expires header set)
- [ ] **Magic link verify** → session regenerated (new session ID issued)

### Card Module
- [ ] **Admin login** → OTP sent, session regenerated on verify
- [ ] **User login** → session created, role set, session regenerated
- [ ] **Logout** → redirects to login.php, cookie cleared
- [ ] **Admin API calls** → require valid CSRF token
- [ ] **Unauthorized** → non-admin cannot call admin API endpoints (e.g., `get_all_specs`)

### R&D API
- [ ] **Rate limit** → 61st request in 1 min returns 429
- [ ] **CORS** → disallowed origin gets no `Access-Control-Allow-Origin` header
- [ ] **API key query string** → fails with 401 (deprecated)
- [ ] **API key via header** → works (X-API-Key or Authorization header)

### Session Cookies
- [ ] **DevTools → Application → Cookies → PHPSESSID**
  - [ ] HttpOnly ✓
  - [ ] Secure ✓ (only on HTTPS)
  - [ ] SameSite=Lax ✓

---

## Implementation Timeline

| Date | Change | File |
|------|--------|------|
| 5/6/2026 | Added `sb_session_start_secure()` | `functions.php` |
| 5/6/2026 | Added `sb_require_csrf()` | `functions.php` |
| 5/6/2026 | CSRF enforcement in main app router | `index.php` |
| 5/6/2026 | Logout cookie clearing | `index.php` |
| 5/6/2026 | Magic link session regeneration | `index.php` |
| 5/6/2026 | Card module hardened (all files) | `card/*` |
| 5/6/2026 | API key query string removed | `functions.php` |
| 5/6/2026 | Smoke test script | `SECURITY_SMOKE_TEST.php` |

---

## Manual Testing Steps (Local)

### 1. Check Session Cookie Flags

```bash
# Terminal
php -S localhost:8000

# Browser DevTools
# 1. Open http://localhost:8000/index.php
# 2. Look for login form
# 3. Log in with admin:password
# 4. Open DevTools (F12)
# 5. Go to Application → Cookies
# 6. Click PHPSESSID
# 7. Verify:
#    ✓ HttpOnly checkbox is checked
#    ✓ Secure checkbox is checked (if HTTPS)
#    ✓ SameSite is set to "Lax" or "Strict"
```

### 2. Test CSRF Protection

```bash
# Terminal 1: Start server
php -S localhost:8000

# Terminal 2: Get a session cookie
SESSION=$(curl -s -c cookies.txt http://localhost:8000/index.php | grep -oP 'PHPSESSID=\K[^;]*')

# Log in
curl -s -X POST -b cookies.txt \
  -d 'action=login&username=admin&password=demo123456' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  http://localhost:8000/index.php

# Try POST without CSRF token (should fail)
curl -s -X POST -b cookies.txt \
  -d 'action=save_post&title=Test' \
  http://localhost:8000/index.php
# Should return: "CSRF validation failed"

# Try with valid token (requires extracting from session)
# Get CSRF from UI or session
```

### 3. Test Logout

```bash
# Log in (see step 2)
# Then:
curl -s -X GET -b cookies.txt \
  "http://localhost:8000/index.php?action=logout"

# Check if cookie is cleared
# Cookie should have an Expires date in the past
```

### 4. Test Card Module

```bash
# 1. Navigate to http://localhost:8000/card/login.php
# 2. Register new account with password < 10 chars
#    → Should fail with "Password must be at least 10 characters"
# 3. Register with valid password
# 4. Log out
# 5. Check DevTools cookies (should be cleared)
```

### 5. Test Rate Limiting

```bash
# Burst 70 requests (should hit limit after 60)
for i in {1..70}; do
  curl -s http://localhost:8000/api_intelligence_rd.php \
    -H "X-API-Key: test_key"
done | tail -5
# Last ~10 requests should show 429 status
```

---

## Files Modified Summary

| File | Changes |
|------|---------|
| `functions.php` | Added `sb_session_start_secure()`, `sb_require_csrf()`, removed query string API key support |
| `index.php` | Added CSRF enforcement, logout cookie clearing, magic link session regeneration |
| `api_intelligence_rd.php` | ✓ Already hardened (CORS, rate limiting) |
| `card/admin_login.php` | Hardened session bootstrap, OTP session regeneration |
| `card/admin.php` | Hardened session bootstrap |
| `card/index.php` | Hardened session bootstrap |
| `card/view.php` | Hardened session bootstrap |
| `card/login.php` | Hardened session bootstrap |
| `card/logout.php` | Hardened session bootstrap |
| `card/function.php` | API now uses `sb_session_start_secure()`, CSRF validation, session regeneration on login |

---

## Known Limitations & Future Work

1. **Rate Limiting:** File-based system is not ideal for distributed/cluster setups. Recommend Redis.
2. **Brute Force Protection:** Not implemented yet (P1 item). Recommend lockout after N failed attempts.
3. **Password Policy:** Only minimum length (10 chars). Consider requiring complexity (uppercase, number, special char).
4. **2FA:** Magic link flow exists but isn't fully wired. Consider TOTP or SMS for production.
5. **API Key Rotation:** Not implemented yet. Recommend adding expiration + rotation flow.
6. **Upload Storage:** Still inside web root. Consider moving to `/var/uploads` or cloud storage.

---

## Conclusion

All P0 security hardening has been successfully implemented and integrated. The application is now ready for:

1. **Code review** - Peer review of changes
2. **Regression testing** - Full end-to-end test suite
3. **Security audit** - Third-party penetration testing
4. **Deployment** - Production rollout with HTTPS enforced

**Next immediate action:** Run the regression test checklist in a staging environment before merging to production.

---

**Report Generated:** May 6, 2026  
**Status:** COMPLETE  
**Ready for Testing:** YES ✅
