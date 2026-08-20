# Verapay

A payment operations dashboard: customer wallet/deposits/withdrawals/transactions/support, plus an admin/operator console with user suspension, a support inbox, and an audit log.

Vanilla PHP (PDO, no framework), MySQL/MariaDB, Tailwind CSS (compiled, no runtime Node dependency), and plain fetch-based JavaScript.

## 1. Project structure

```
/public                 Document root — point your web server here
  index.php             Front controller / router (reads the route from the URL path)
  .htaccess             Apache rewrite + security headers
  /api                  JSON endpoints, grouped by feature
  /assets/css           tokens.css (design tokens) + app.css (Tailwind source) + app.build.css (compiled, generated)
  /assets/js            app.js (shared: toasts, modals, dropdowns) + charts.js + one file per page
  /assets/icons         icons.php — inline SVG icon() helper

/config                 config.php, database.php, env.php — outside the document root, include-only
/includes               auth.php (session/CSRF/suspension middleware), money.php (bcmath helpers),
                         functions.php, and shared layout partials (header/sidebar/navbar/footer/modal)
/pages                  View templates included by public/index.php (not directly web-accessible)
/database               schema.sql + seed.php (demo data)

.env.example            Copy to .env and fill in real values
tailwind.config.js, package.json   Build-time only — Tailwind CLI, no Node needed to run the app
docker-compose.yml, docker/        Local dev convenience (MySQL + PHP containers) — not required for real hosting
```

**Why config/includes/pages sit outside `/public`:** they're PHP that should never be served as a raw file if a server is misconfigured. Point your document root at `/public`; everything else is reached only through `require`.

## 2. Requirements

- PHP 8.1+ with `pdo_mysql` and `bcmath` extensions
- MySQL 8.0+ or MariaDB 10.6+
- Node.js (build time only, to compile Tailwind CSS — not required to run the app)
- Apache with `mod_rewrite` (or Nginx — see §6), or PHP's built-in server for local dev

## 3. Quick start with Docker (recommended — this is what gets tested)

The only prerequisite is Docker Desktop. No PHP or MySQL install needed on your machine at all.

```bash
git clone <your-repo-url> verapay && cd verapay
cp .env.example .env                          # defaults already match docker-compose.yml
docker compose up -d --build                  # starts MySQL + PHP; schema.sql auto-imports on first run
docker compose exec app php database/seed.php # creates demo accounts (see §7)
```

Open **http://localhost:8080/login**. If port 8080 is already taken on your machine, change the `8080:8000` line in `docker-compose.yml` and update `APP_URL` in `.env` to match.

```bash
docker compose down       # stop (DB data persists in a named volume)
docker compose logs -f app  # watch request logs
```

## 4. Install without Docker

For deploying to real PHP hosting, or if you'd rather run PHP/MySQL natively:

```bash
# 1. Build tooling — no Composer packages, just the Tailwind CLI (skip this if you're
#    not changing styles; app.build.css is already committed and works as-is):
npm install
npm run build:css        # compiles public/assets/css/app.build.css

# 2. Configure environment
cp .env.example .env
# edit .env: set DB_HOST to your MySQL server (see the comment in .env.example)

# 3. Create the database schema
mysql -u root -p -e "CREATE DATABASE verapay CHARACTER SET utf8mb4"
mysql -u root -p verapay < database/schema.sql

# 4. Seed demo data (creates the accounts below with properly bcrypt-hashed passwords)
php database/seed.php
```

## 5. Environment variables (`.env`)

| Variable | Purpose |
|---|---|
| `APP_NAME`, `APP_URL` | Display name and base URL |
| `APP_ENV`, `APP_DEBUG` | `APP_DEBUG=false` in production — suppresses PHP error output (errors are still logged) |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection |
| `SESSION_LIFETIME_MINUTES` | Idle session timeout |
| `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES` | Login rate limiting |

Credentials are never hard-coded anywhere in the codebase — only read from `.env` via `config/env.php`.

## 6. Run locally (without Docker)

```bash
php -S localhost:8000 -t public
```

PHP's built-in server automatically falls back to `public/index.php` for any URL that isn't a real file, so pretty URLs (`/dashboard`, `/wallet`, …) work without extra setup. `public/api/*.php` files are served directly since they exist as real files.

## 7. Deploy (conventional PHP hosting)

1. Point the domain/subdomain's **document root at `/public`**. On most shared hosting (cPanel etc.) this is a document-root setting, not a folder you move.
2. Ensure `mod_rewrite` is enabled — `public/.htaccess` handles routing and sets basic security headers.
   - **Nginx** equivalent:
     ```nginx
     location / {
         try_files $uri $uri/ /index.php;
     }
     location ~ \.php$ {
         fastcgi_pass unix:/run/php/php8.3-fpm.sock;
         fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
         include fastcgi_params;
     }
     ```
3. Set `.env` with production DB credentials and `APP_DEBUG=false`.
4. Run `npm run build:css` before deploying (or commit `app.build.css`) — the app never fetches Tailwind at runtime.
5. Serve over HTTPS — session cookies are marked `Secure` automatically once the request arrives over HTTPS (see `includes/auth.php`).
6. If your host can't point the document root at `/public`, the root `.htaccess` blocks direct access to `config/`, `includes/`, `pages/`, and `database/` as a defense-in-depth fallback — but a correctly set document root is the supported path.

## 8. Demo accounts

Created by `php database/seed.php`. Password for all: **`Demo!2024pass`**

| Role | Email |
|---|---|
| Admin | `admin@verapay.test` |
| Operator | `operator@verapay.test` |
| Customer | `priya@verapay.test` |
| Customer | `jonah@verapay.test` |

These are demo-only accounts seeded into your own local/staging database — not credentials to any external service.

## 9. Database schema

See `database/schema.sql` for full DDL. Summary:

- **users** — role (`customer`/`operator`/`admin`), status (`active`/`suspended`)
- **wallets** — one per user; `available_balance` / `pending_balance`, both `DECIMAL(18,2)`
- **transactions** — unified deposit/withdrawal ledger (`type` column); Deposits and Withdrawals pages are filtered views of this table
- **support_conversations** / **support_messages** — persisted chat, shared between customer and operator views
- **notifications** — per-user, `is_read` flag
- **payment_gateways** — configured processors; only a one-way hash + last 4 characters of each API key are ever stored
- **audit_logs** — actor, action, target, metadata, IP, timestamp
- **login_attempts** — backs login rate limiting

All monetary values are `DECIMAL`, never float. Fee/balance math runs through `includes/money.php`, which uses `bcmath` on string-typed amounts exclusively. Currency defaults to `INR` (₹).

## 10. Authentication & security model

- PHP native sessions; `session_regenerate_id()` on login (fixation prevention); idle timeout via `SESSION_LIFETIME_MINUTES`; cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS.
- CSRF token minted per session, sent as `X-CSRF-Token` on every mutating `fetch()`, verified server-side on every `POST`/`PUT`/`PATCH`/`DELETE`.
- **`require_auth()`** (in `includes/auth.php`) is called at the top of every page and every API endpoint. It re-queries the user's live `status` from the database on every single request — this is the entire mechanism behind suspension taking effect immediately: there's no session-token table to separately revoke, because the status check itself gates every request.
- All queries use PDO prepared statements with `PDO::ATTR_EMULATE_PREPARES => false` (real server-side prepares).
- Output is escaped via `e()` (an `htmlspecialchars` wrapper) everywhere user data reaches HTML.
- IDOR checks: support conversations/messages verify the requesting user owns the conversation (or is staff) before returning anything; notifications are scoped to `user_id` on every read/write.
- Login attempts are rate-limited per email+IP with a rolling lockout window.

## 11. API reference

All endpoints return `{ "success": bool, "data": ..., "message": "..." }`. Mutating endpoints require the `X-CSRF-Token` header. All require an authenticated session unless noted.

| Endpoint | Method | Notes |
|---|---|---|
| `/api/auth/login.php` | POST | Public. Rate-limited. |
| `/api/auth/logout.php` | POST | |
| `/api/dashboard/summary.php` | GET | Scoped to caller; admins/operators get platform-wide figures |
| `/api/wallet/summary.php` | GET | Customer only |
| `/api/deposits/create.php` | POST | Customer only. Server recalculates fee/net amount — never trusts client math |
| `/api/withdrawals/create.php` | POST | Customer only. Row-locks the wallet, rejects amount+fee > available balance |
| `/api/transactions/list.php` | GET | Filters: `type`, `status`, `from`, `to`, `search`, `sort`, `page`, `per_page` |
| `/api/support/conversations.php` | GET/POST | GET scoped to caller (or all, for staff); POST creates a conversation (customer) |
| `/api/support/messages.php` | GET/POST | `conversation_id` ownership enforced server-side (IDOR guard) |
| `/api/notifications/list.php` | GET | |
| `/api/notifications/mark-read.php` | POST | `{id}` or `{all:true}` |
| `/api/admin/users/list.php` | GET | Admin/operator only |
| `/api/admin/users/suspend.php` | POST | Admin only. Cannot suspend self or another admin |
| `/api/admin/users/reactivate.php` | POST | Admin only |
| `/api/profile/update.php` | POST | |
| `/api/settings/change-password.php` | POST | Requires current password |
| `/api/admin/gateways/list.php` | GET | Admin only |
| `/api/admin/gateways/create.php` | POST | Admin only. Stores a hash + last 4 chars only, never the full key |
| `/api/admin/gateways/update-status.php` | POST | Admin only. Blocks deactivating the current default |
| `/api/admin/gateways/set-default.php` | POST | Admin only. Must already be active |
| `/api/admin/gateways/rotate-key.php` | POST | Admin only |
| `/api/admin/gateways/delete.php` | POST | Admin only. Blocks deleting the current default |

## 12. What's been tested

This build was exercised end-to-end against a real MySQL instance (Docker) rather than only reviewed statically. Verified: login/logout/rate-limit, dashboard + wallet data for both roles, deposit and withdrawal creation (including fee math and insufficient-balance rejection), transaction filtering/search/sort/pagination, the full support-reply loop across two separate sessions (customer sends → admin inbox shows it → admin replies → customer's next fetch shows the reply → notification created), IDOR rejection on a second customer trying to read another customer's conversation, the admin suspend flow invalidating an already-logged-in customer session on its very next request (page nav and API call both verified), CSRF rejection on a token-less mutation, and role-based 403s on customer access to admin routes.

Not yet done, and worth doing before a real launch: a screen-reader pass, a formal WCAG audit tool run, and a dependency/penetration review — the code follows the practices in §10 but hasn't been adversarially tested beyond the IDOR/CSRF/suspension/rate-limit checks above.
