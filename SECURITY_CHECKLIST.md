# Socialbrain Security Checklist (Actionable)

## Secrets & configuration
- **Remove secrets from repo**: `.htaccess` must not contain DB credentials (done). Configure `SB_DB_HOST`, `SB_DB_NAME`, `SB_DB_USER`, `SB_DB_PASS` at the server/environment level.
- **Rotate credentials** if any were committed previously.

## Authentication & authorization
- **Card Builder**: enforce admin session for admin-only API actions (done in `card/function.php`).
- **Card Builder**: store passwords as `password_hash` and verify with `password_verify` (done; legacy plaintext auto-upgrades on login).
- **Main app**: keep session fixation protections (`session_regenerate_id(true)` already present on login).
- **API keys**: **header-only** (`X-API-Key`) or `Authorization: Bearer` (query-string keys removed).

## CORS / CSRF
- **Do not use `Access-Control-Allow-Origin: *`** on authenticated endpoints.\n  - Implement allowlist origins via env `SB_CORS_ALLOW_ORIGINS` (done in `api_intelligence_rd.php`).\n  - Ensure `Vary: Origin` is set when reflecting origins (done).
- **Consider CSRF tokens** for browser session POST endpoints (main app + card app) if they are intended for cross-site use.

## File uploads
- **Validate by server-detected MIME**, not client filename/extension (done for R&D uploads and card textures).\n- **Restrict max size** (R&D: 15MB; card textures: 5MB).\n- **Use safe extensions derived from MIME** (done for R&D uploads and card textures).\n- **Prevent script execution in upload directories**: add `.htaccess` deny rules (done for `uploads/research/`).\n- **Avoid `0777` directories** (changed to `0755`).

## Rate limiting / abuse prevention
- **Fix rate limiter accounting** per IP and enforce locking on persistence (done in `api_intelligence_rd.php`).\n- Consider moving rate limiting to a shared store (Redis) if you’ll run multiple PHP workers/servers.

## Logging & PII
- Avoid logging secrets/passwords.\n- Confirm which fields are stored in JSON and ensure protected files are not web-accessible (JSON deny in `.htaccess` exists).

