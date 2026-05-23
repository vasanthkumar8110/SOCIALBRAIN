# Socialbrain Master Tester Report

## Executive summary (top risks)
- **P0: Secrets committed in `.htaccess`** (DB creds) → compromise risk. **Mitigated** by removing secrets from repo; rotate creds.
- **P0: Card Builder stored plaintext passwords + weak admin creation key** → credential theft / trivial admin creation. **Mitigated** by hashing passwords + env-based admin secret + admin-only API enforcement.
- **P0: Authenticated R&D endpoint had `Access-Control-Allow-Origin: *`** → cross-site abuse with user session/API key contexts. **Mitigated** by allowlist CORS.
- **P1: R&D rate limiter ineffective** → abuse/DoS potential. **Mitigated** by per-IP timestamp buckets + `LOCK_EX`.
- **P1: Upload hardening gaps** (extension trust, permissive perms, missing execution protections). **Mitigated** with MIME→ext mapping, size limits, `0755`, and upload `.htaccess`.

## System map (what exists)

### Entrypoints
- **Main app**: `index.php` (single router with many `action=` handlers), helpers in `functions.php`
- **R&D API**: `api_intelligence_rd.php` (authenticated analytics + news + upload/export)
- **Card Builder app**: `card/index.php`, `card/admin.php`, `card/admin_login.php`, API embedded in `card/function.php`

### Main app action inventory (selected)
From `index.php`:\n
- **Auth/public**: `login` (POST), `signup` (POST), `recover_password` (POST), `logout`\n
- **Admin/team**: `register_admin` (POST), `create_invite` (POST), `register_invited` (POST)\n
- **User mgmt (admin)**: `save_user` (POST), `delete_user` (POST), `get_users` (GET)\n
- **Data/content**: `get_data` (GET), `save_post` (POST), `delete_post` (POST), `restore_post` (POST), `save_product` (POST), taxonomy saves/deletes, tasks saves/deletes, bulk uploads, logs export/restore, destructive “delete_all_*” actions.\n

### R&D action inventory
From `api_intelligence_rd.php` switch:\n
- `overview`, `api_analytics`, `activity_intelligence`, `realtime`, `ml_insights`, `security`, `news`, `research_upload_image`, `research_list_images`, `export`

### Card Builder API action inventory
From `card/function.php` switch:\n
- `login`, `register`, `register_secure`, `get_security_question`, `reset_password`\n
- `get_materials`, `get_compatible_options`, `save_specification`, `get_user_specs`\n
- `upload_texture`\n
- **Admin**: `get_all_specs`, `update_spec_status`, `add_material`, `update_material`, `delete_material`, `save_ui_config`, `create_project`, `rename_project`, `delete_project`

## Prioritized defects & fixes (Master Tester style)

### P0 — Secrets in repo via `.htaccess`
- **Location**: `.htaccess`\n
- **Issue**: DB creds were committed (`SB_DB_PASS 1234`).\n
- **Impact**: credential exposure, lateral movement, database compromise.\n
- **Fix**: removed secrets from repo; require server/env configuration.\n
- **Status**: **Fixed in this change**.

### P0 — Card Builder plaintext passwords
- **Location**: `card/function.php` (`authenticateUser`, `createUser`, `createUserSecure`, `resetPassword`)\n
- **Issue**: Passwords stored and compared as plaintext.\n
- **Impact**: immediate credential disclosure if JSON leaked; no safe password storage.\n
- **Fix**: switched to `password_hash` + `password_verify` with legacy auto-upgrade.\n
- **Status**: **Fixed in this change**.

### P0 — Card Builder admin creation uses static shared key
- **Location**: `card/admin_login.php`\n
- **Issue**: Admin creation guarded by constant `admin123`.\n
- **Impact**: anyone can create admin if page accessible.\n
- **Fix**: require env `CARD_ADMIN_SECRET_KEY`, otherwise registration disabled.\n
- **Status**: **Fixed in this change**.

### P0 — R&D CORS wildcard on authenticated endpoint
- **Location**: `api_intelligence_rd.php`\n
- **Issue**: `Access-Control-Allow-Origin: *` enabled with session/API-key auth.\n
- **Impact**: cross-site abuse and data exfil patterns.\n
- **Fix**: allowlist origins via env `SB_CORS_ALLOW_ORIGINS`; respond to OPTIONS.\n
- **Status**: **Fixed in this change**.

### P1 — Broken R&D rate limiting
- **Location**: `api_intelligence_rd.php`\n
- **Issue**: stored keys `ip_timestamp`, but filtered by `$ip === $clientIP` so it didn’t count correctly.\n
- **Impact**: ineffective abuse protection.\n
- **Fix**: per-IP array of timestamps, cleanup, `LOCK_EX` persistence.\n
- **Status**: **Fixed in this change**.

### P1 — Upload execution risk & extension trust
- **Locations**:\n
  - `api_intelligence_rd.php` (`research_upload_image`)\n
  - `card/function.php` (`upload_texture`)\n
- **Issues**:\n
  - trusted client extension; permissive directory permissions; missing execution guards.\n
- **Impact**: risk of storing dangerous files; potential remote execution depending on server config.\n
- **Fix**:\n
  - MIME→extension mapping, size limits, `0755` dirs\n
  - added `uploads/research/.htaccess` to deny script-like extensions\n
  - card texture upload now MIME validates + safe ext.\n
- **Status**: **Fixed in this change**.

### P2 — API key accepted via query string
- **Location**: `functions.php` (`checkAuthAndGetRole`) accepted `?api_key=`.\n
- **Impact**: key leakage via logs/referrers.\n
- **Status**: **Fixed** — header-only (`X-API-Key`) and `Authorization: Bearer` supported.\n

## Test matrix (minimum regression suite)

### Auth & session (main app)
- **Login success**: valid username/password → session set, redirect.\n
- **Login failure**: invalid credentials → error message.\n
- **Session timeout**: after `SESSION_TIMEOUT` inactivity → forced logout.\n
- **RBAC**: viewer cannot call editor/admin actions; editor cannot call destructive actions.\n

### Invites / hierarchy (main app)
- **Create invite (admin only)**: returns link + token; token expires in 7 days.\n
- **Register invited**: valid token creates user with role; token removed.\n
- **Expired token**: fails with “Invite link expired”.\n

### R&D API
- **Auth required**: without session/API-key → 401.\n
- **CORS allowlist**: request from allowed origin gets `Access-Control-Allow-Origin` reflected; disallowed origin gets no header.\n
- **Rate limit**: >60 requests/min from same IP → 429.\n
- **Upload**: valid image/video (<=15MB) uploads; invalid MIME rejected; filename extension matches MIME.\n

### Card Builder
- **User register + login**: password stored hashed; login works.\n
- **Legacy user login**: if existing plaintext password matches, login succeeds and password upgrades to hash.\n
- **Admin API enforcement**: calling `save_ui_config` etc without admin session returns unauthorized.\n
- **Texture upload**: only JPG/PNG/WEBP under 5MB.\n

## Notes for deployment/config
- Set these environment variables in your Apache/Laragon environment:\n
  - `SB_DB_HOST`, `SB_DB_NAME`, `SB_DB_USER`, `SB_DB_PASS`\n
  - `SB_CORS_ALLOW_ORIGINS` (comma-separated)\n
  - `CARD_ADMIN_SECRET_KEY`\n

