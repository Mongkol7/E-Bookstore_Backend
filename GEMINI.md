# GEMINI.md

## Project Overview

This project is a custom PHP API with a React frontend for an e-commerce bookstore.

- Backend entry point: `public/index.php`
- Backend routing: `routes/web.php`
- Controllers: `app/Controllers`
- Repositories: `app/Repositories`
- Models: `app/Models`
- Frontend app: `frontend/` (React + Vite + React Router)

## Current Stack

- Backend: PHP (custom MVC-like structure)
- Frontend: React, Vite, Tailwind-style utility classes
- Database: PostgreSQL (Supabase or Railway)
- Auth token: JWT in `HttpOnly` cookie (`auth_token`)

## Environment

### Backend `.env`

```env
DB_DRIVER=pgsql
DB_SSLMODE=require

# Option A: Supabase style
DB_HOST=aws-1-ap-northeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.hpxsnvwvwyzydpryjyni
DB_PASSWORD=YOUR_SUPABASE_DB_PASSWORD

# Option B: Railway style (supported directly)
DATABASE_URL=postgresql://postgres:YOUR_PASSWORD@YOUR_RAILWAY_PRIVATE_DOMAIN:5432/railway
# or
DATABASE_PUBLIC_URL=postgresql://postgres:YOUR_PASSWORD@YOUR_RAILWAY_TCP_PROXY_DOMAIN:YOUR_RAILWAY_TCP_PROXY_PORT/railway

JWT_SECRET=YOUR_JWT_SECRET

# CORS/Cookie for local + deployment
CORS_ALLOWED_ORIGINS=https://your-vercel-app.vercel.app
CORS_ALLOW_VERCEL_PREVIEWS=true
COOKIE_SAMESITE=None
COOKIE_DOMAIN=
COOKIE_PATH=/

# Purchase alert email (owner notification)
MAIL_ENABLED=true
MAIL_PROVIDER=auto
MAIL_TIMEOUT=20
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME=Ecommerce Store
PURCHASE_ALERT_TO=owner@example.com
PURCHASE_ALERT_SUBJECT_PREFIX=[New Purchase]

# Recommended transport on Railway (HTTPS)
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxx
RESEND_API_URL=https://api.resend.com/emails

# Optional SMTP fallback transport
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
```

### Frontend `frontend/.env`

```env
VITE_API_BASE_URL=http://localhost:3000
```

Notes:
- Supabase/Railway are database providers; backend API must still be running.
- XAMPP MySQL is not required unless `DB_DRIVER` is changed.

## Run Instructions (Local)

1. Install PHP dependencies:

```bash
composer install
```

2. Start backend API:

```bash
php -S localhost:3000 -t public
```

3. Start frontend:

```bash
cd frontend
npm install
npm run dev
```

Frontend dev URL is typically `http://localhost:5173`.

## Core Architecture Process

### Backend request lifecycle (execution order)

1. `public/index.php`
- Loads Composer autoload.
- Loads `.env` early.
- Builds CORS allowlist (localhost + `CORS_ALLOWED_ORIGINS`, optional `*.vercel.app`).
- Sends CORS headers and handles preflight `OPTIONS`.
- Includes `routes/web.php`.

2. `routes/web.php`
- Normalizes incoming path and method.
- Matches route via `if/elseif` tree.
- Calls `AuthMiddleware::authenticate()` for protected endpoints.
- Dispatches to controller methods.

3. Controller layer (`app/Controllers/*`)
- Parses JSON payload.
- Validates required fields.
- Calls repository/business logic.
- Returns HTTP status and JSON response.

4. Repository layer (`app/Repositories/*`)
- Executes SQL with PDO.
- Maps DB rows to model objects.

5. Model layer (`app/Models/*`)
- Represents typed domain entities passed between repositories and controllers.

6. Database connection (`config/database.php`)
- Initializes and reuses PDO.
- Supports `DATABASE_URL`/`DATABASE_PUBLIC_URL` parsing.
- Supports PostgreSQL (default) and MySQL DSN.

### Auth/Cookie process

- JWT generation/verification: `app/Helpers/JwtHelper.php`
- Cookie option policy: `app/Helpers/CookieHelper.php`
- Token resolution and auth context injection: `app/Middleware/AuthMiddleware.php`

`AuthMiddleware` resolves token from:
1. `Authorization: Bearer <token>` header
2. `auth_token` cookie

If valid, decoded payload is stored in `$_SERVER['user']` for downstream controllers.

### Frontend execution order

1. `frontend/src/main.jsx` mounts React app.
2. `frontend/src/App.jsx` maps URL paths to page components.
3. Pages call backend through `frontend/src/utils/api.js`.
4. Auth token persistence and cleanup are handled by `frontend/src/utils/auth.js`.

## Auth Behavior (Current)

### Signup (`/signup`)

- Customer-only signup
- Calls `POST /api/customers/post`
- Sends: `first_name`, `last_name`, `email`, `phone`, `address`, `password`
- Password is hashed before storage

### Login (`/login`)

- Frontend calls unified endpoint: `POST /api/login`
- Backend checks customer/admin in one request
- Frontend uses `credentials: include`
- Token is set as `auth_token` cookie and also returned in JSON

### Profile / Logout

- `GET /api/auth/profile`
- `POST /api/logout`

## Cookie + CORS (Current)

- Cookie name: `auth_token`
- CORS supports local origins + configured origins from `CORS_ALLOWED_ORIGINS`
- Optional wildcard support for Vercel previews via `CORS_ALLOW_VERCEL_PREVIEWS=true`
- Cookie options are centralized via `app/Helpers/CookieHelper.php`
- For cross-site deployment (Vercel frontend + separate backend), use `SameSite=None` + HTTPS

## API Endpoints (Core)

### Public

- `GET /api/books`
- `GET /api/books/{id}`

### Auth

- `POST /api/login`
- `GET /api/auth/profile`
- `POST /api/logout`
- `POST /api/customers/post`
- `POST /api/customers/login` (legacy path, still present)
- `POST /api/admins/login` (legacy path, still present)

### Cart / Orders

- `GET /api/cart`
- `POST /api/cart/add`
- `PUT /api/cart/quantity`
- `DELETE /api/cart/remove`
- `POST /api/cart/checkout`
- `GET /api/orders`
- `GET /api/orders/{orderId}`

## Order Persistence Model (PostgreSQL)

User-specific data is stored in JSONB:

- `customers.order_history`
- `admins.processed_orders`

Normalized shape:

```json
{
  "cart": [],
  "orders": []
}
```

Backward compatibility exists for legacy array-only JSON.

## Performance Notes

- Database connection uses tuned PDO settings in `config/database.php`.
- Admin/customer email lookups are case-insensitive.
- Unified `/api/login` reduces auth round-trips.
- Recommended DB indexes are in `database/supabase_performance.sql`.
- Checkout no longer performs direct SMTP send in request path.
- Purchase alerts are queued in DB and processed asynchronously by worker cron.

## Frontend Routes

- `/` -> Homepage
- `/product/:bookId` -> Product detail page
- `/login` -> Login page
- `/signup` -> Signup page
- `/cart` -> Cart page
- `/checkout` -> Checkout page
- `/orders` -> Orders list
- `/orders/:orderId` -> Order detail page

## Detailed End-to-End Sequence Flows

### Flow A: Login

1. User submits login form in `frontend/src/pages/Auth/Login/index.jsx`.
2. Frontend sends `POST /api/login` with `{ email, password }`.
3. `public/index.php` applies CORS and forwards to `routes/web.php`.
4. `routes/web.php` dispatches to `AuthController::login()`.
5. Controller queries customer and admin tables (case-insensitive email).
6. Password is verified (hashed or legacy plaintext fallback).
7. On success:
- `last_login` updated.
- JWT created by `JwtHelper`.
- `auth_token` cookie set via `CookieHelper`.
- JSON response includes user profile and token.
8. Frontend stores token using `saveAuthToken()` and navigates to `/`.

### Flow B: Load homepage + books + profile

1. `frontend/src/pages/Home/Homepage/index.jsx` mounts.
2. Shared navbar (`frontend/src/components/StoreNavbar.jsx`) calls `GET /api/auth/profile`.
3. Backend route requires `AuthMiddleware::authenticate()`.
4. Middleware resolves token from Bearer/cookie and injects `$_SERVER['user']`.
5. `AuthController::getProfile()` loads user through `CustomerRepository` or `AdminRepository`.
6. Frontend updates navbar profile name/role.
7. Frontend calls `GET /api/books`.
8. `BookController::getAllBooks()` -> `BookRepository::getAll()`.
9. Repository returns books with author/category joins, optional rating/sales columns.
10. Homepage renders searchable/filterable book grid.

### Flow C: Add to cart

1. User clicks Add to Cart in homepage.
2. Frontend sends `POST /api/cart/add` with `{ book_id, quantity }`.
3. Route requires auth middleware.
4. `CartController::addToCart()`:
- Loads user storage (`order_history` or `processed_orders`).
- Validates book existence and stock.
- Adds/merges cart item.
- Persists normalized JSONB payload.
5. Frontend shows success/failure feedback.

### Flow D: Cart quantity update/remove

1. Cart page loads via `GET /api/cart`.
2. `CartController::getCart()` hydrates item metadata (stock/category) and returns `items`.
3. Quantity change sends `PUT /api/cart/quantity`.
4. Controller validates quantity and stock, updates JSONB cart.
5. Remove sends `DELETE /api/cart/remove`.
6. Controller filters out item and persists updated cart.

### Flow E: Checkout

1. Checkout page receives cart items from route state.
2. Page optionally auto-fills user profile via `GET /api/auth/profile`.
3. User completes shipping/payment UI steps.
4. Final submit sends `POST /api/cart/checkout` with shipping and payment method metadata.
5. `CartController::checkout()`:
- Validates authenticated user and non-empty cart.
- Revalidates each book stock.
- Computes subtotal, tax, shipping, total.
- Creates order object.
- Starts DB transaction.
- Decrements book stock.
- Increments `sales_count` or `sold` if column exists.
- Prepends order into orders array.
- Clears cart.
- Saves JSONB payload and commits.
 - After successful commit, inserts purchase-alert job into `purchase_alert_outbox`.
 - Returns response with `mail_status: "queued"` (and optional queue id).
6. Frontend shows success and redirects to cart/orders path.

### Flow F: Order history and order detail

1. Orders page calls `GET /api/orders`.
2. `CartController::getOrders()` loads user orders and hydrates embedded items.
3. Page lists orders with totals/date and links to `/orders/:orderId`.
4. Detail page calls `GET /api/orders/{orderId}`.
5. `CartController::getOrderById()` returns exact order payload.
6. Detail page renders timeline/items/shipping/payment blocks.

### Flow G: Purchase-alert queue worker

1. Railway worker service `purchase-alert-queue-worker` runs cron schedule `*/5 * * * *`.
2. Start command:
   `php -d variables_order=EGPCS scripts/process_purchase_alert_queue.php --limit=20 --max-attempts=6`
3. Worker claims due jobs with `FOR UPDATE SKIP LOCKED`.
4. For each job:
   - sends alert email via configured provider (`MAIL_PROVIDER=auto` => Resend if key exists),
   - marks `sent` on success,
   - or increments attempts and schedules backoff on transient failure,
   - marks `failed` when max attempts exceeded.
5. Queue stats can be checked with:
   `php scripts/purchase_alert_queue_stats.php`

## File Responsibilities (Quick Map)

### Backend

- `public/index.php`: CORS bootstrap + route handoff.
- `routes/web.php`: path/method dispatch table.
- `app/Middleware/AuthMiddleware.php`: route protection.
- `app/Helpers/JwtHelper.php`: token encode/decode.
- `app/Helpers/CookieHelper.php`: secure cookie option strategy.
- `app/Controllers/*.php`: input validation + response behavior.
- `app/Repositories/*.php`: SQL and persistence.
- `app/Models/*.php`: domain object shape.
- `config/database.php`: PDO config/bootstrap.

### Frontend

- `frontend/src/main.jsx`: app bootstrap.
- `frontend/src/App.jsx`: client routes.
- `frontend/src/utils/api.js`: API URL build + authenticated fetch.
- `frontend/src/utils/auth.js`: token persistence/session cleanup.
- `frontend/src/pages/...`: per-screen logic and request flow.
- `frontend/src/components/StoreNavbar.jsx`: shared top navigation (logo, cart link, profile menu, logout modal) used across store pages.
- `frontend/src/components/Skeleton.jsx`: loading placeholders.
- `frontend/src/components/Footer.jsx`: global footer UI.

## Troubleshooting

- `Unable to connect to server` (localhost):
  - Confirm backend is running (`php -S localhost:3000 -t public`).
  - Confirm `frontend/.env` has `VITE_API_BASE_URL=http://localhost:3000`.

- `404` on `https://<vercel>.vercel.app/api/...`:
  - `VITE_API_BASE_URL` is missing in Vercel env.
  - Set it to your public backend domain and redeploy.

- Cookie/session issues on deployment:
  - Ensure backend is HTTPS.
  - Set `COOKIE_SAMESITE=None`.
  - Ensure `CORS_ALLOWED_ORIGINS` includes frontend domain.

- `Invalid credentials`:
  - Ensure password is sent as a string, e.g. `"123"`.

- Purchase alert rows stay `pending`/`failed`:
  - Check worker service exists and cron is configured on `purchase-alert-queue-worker` (not API service).
  - Check worker start command includes `-d variables_order=EGPCS`.
  - Confirm `RESEND_API_KEY` is present on both `E-Bookstore_Backend` and `purchase-alert-queue-worker`.
  - Confirm `MAIL_FROM_ADDRESS` is valid for your Resend account (or `onboarding@resend.dev` for test mode).
  - Requeue failed rows after config fix:
    `UPDATE purchase_alert_outbox SET status='pending', next_attempt_at=NOW(), updated_at=NOW() WHERE status='failed';`

- `SMTP Error: Could not connect to SMTP host` on Railway:
  - This is a provider connectivity issue (common with SMTP from cloud containers).
  - Use HTTPS provider transport (Resend API) instead of SMTP.

## Notes

- Legacy login endpoints (`/api/customers/login`, `/api/admins/login`) still exist for backward compatibility.
- Unified `/api/login` is the preferred frontend path.
- Cart and orders are persisted in JSONB under user records, not separate normalized order tables.
