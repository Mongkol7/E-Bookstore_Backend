# GEMINI.md

## Purpose Of This Document

This file explains how the full E-Bookstore system works, from request entry to database writes to frontend rendering.

It is written for:
- developers who need to understand the codebase quickly,
- maintainers debugging production issues,
- contributors adding features without breaking existing behavior.

The goal is to make each major process explicit and easy to follow.

## System Summary

This project is a full-stack bookstore:
- Backend: custom PHP API (no full framework)
- Frontend: React + Vite + React Router
- Database: PostgreSQL (Supabase or Railway)
- Auth: JWT + HttpOnly cookie + optional Bearer token

Core domains:
- Authentication and profiles
- Books, authors, categories
- Cart and checkout
- Orders and order details
- Admin dashboard analytics and management
- Asynchronous purchase alert email queue

## Repository And File Map

### Backend root
- `public/index.php`: backend entrypoint, CORS policy, preflight handling, route handoff
- `routes/web.php`: path/method dispatcher for all API endpoints
- `config/database.php`: PDO bootstrap and connection strategy (supports `DATABASE_URL` and direct DB vars)
- `app/Middleware/AuthMiddleware.php`: protects routes and injects auth context in `$_SERVER['user']`
- `app/Helpers/JwtHelper.php`: JWT issue/verify
- `app/Helpers/CookieHelper.php`: cookie options (`SameSite`, `Secure`, path/domain)
- `app/Helpers/MailHelper.php`: email delivery abstraction (Resend API or SMTP fallback)

### Controllers

- `app/Controllers/AuthController.php`: login/profile/logout unified auth flow
- `app/Controllers/BookController.php`: books CRUD + stock-only restock endpoint
- `app/Controllers/AuthorController.php`: author CRUD
- `app/Controllers/CategoryController.php`: category CRUD
- `app/Controllers/CustomerController.php`: customer CRUD + legacy auth endpoint
- `app/Controllers/AdminController.php`: admin CRUD + legacy auth endpoint
- `app/Controllers/CartController.php`: cart lifecycle, checkout, orders, admin dashboard order aggregation, outbox enqueue

### Repositories and models

- `app/Repositories/*.php`: SQL statements and row mapping
- `app/Models/*.php`: typed domain objects used by repositories/controllers

### Queue and operational scripts

- `scripts/process_purchase_alert_queue.php`: cron worker that claims/sends/retries outbox jobs
- `scripts/purchase_alert_queue_stats.php`: operational queue summary output
- `database/purchase_alert_outbox.sql`: outbox table/index DDL
- `database/supabase_performance.sql`: index/performance SQL helper set

### Frontend

- `frontend/src/main.jsx`: app bootstrap
- `frontend/src/App.jsx`: route definitions
- `frontend/src/utils/api.js`: API URL builder and authenticated fetch wrapper
- `frontend/src/utils/auth.js`: token storage and cleanup logic
- `frontend/src/components/AdminRoute.jsx`: admin page route guard
- `frontend/src/components/StoreNavbar.jsx`: shared responsive nav bar and profile/logout control
- `frontend/src/components/BookSearchControls.jsx`: reusable search/scope/filter control used in Homepage and Admin Management lists
- `frontend/src/components/ProcessingOverlay.jsx`: loading overlay UI component
- `frontend/src/components/Skeleton.jsx`: loading placeholders
- `frontend/src/pages/...`: feature pages (Auth, Home, Cart, Checkout, Orders, Order detail, Product detail, Dashboard)

## Runtime Architecture

## Backend runtime pipeline

Every API request passes this path:

1. `public/index.php`
- Loads Composer autoload and `.env`
- Builds CORS allow-list from defaults + `CORS_ALLOWED_ORIGINS`
- Optionally allows Vercel preview domains when enabled
- Handles `OPTIONS` preflight immediately
- Includes `routes/web.php`

2. `routes/web.php`
- Normalizes path and method
- Matches endpoint by `if/elseif`
- Runs `AuthMiddleware::authenticate()` on protected routes
- Calls controller method

3. Controller (`app/Controllers/*`)
- Parses JSON payload
- Validates required fields and business rules
- Calls repository or business helpers
- Sends HTTP status + JSON response

4. Repository (`app/Repositories/*`)
- Runs SQL using PDO prepared statements
- Converts DB rows into models/arrays

5. Database
- PostgreSQL persistence and transaction control
- JSONB-based cart/order storage per user

## Frontend runtime pipeline

1. `frontend/src/main.jsx` mounts React app.
2. `frontend/src/App.jsx` maps routes to pages.
3. `frontend/src/utils/api.js` sends requests with:
- `Authorization: Bearer <token>` if token exists
- `credentials: include` for cookie support
4. Pages parse responses and update local state/UI.
5. `StoreNavbar` and `AdminRoute` call profile endpoint to adapt UI by role.

## Authentication And Session Model

## Token sources and route protection

`AuthMiddleware` accepts token from either:
- `Authorization: Bearer ...` header
- `auth_token` cookie

If valid, decoded JWT payload is exposed via `$_SERVER['user']`.
If invalid/missing, API returns `401`.

## Login behavior

Preferred login endpoint:
- `POST /api/login`

Backend checks both customer/admin records and returns role-aware profile data.
On success:
- JWT cookie is set (HttpOnly)
- token also returned in response for frontend storage fallback

Frontend stores token using `frontend/src/utils/auth.js`:
- localStorage when remember-me style persistence is desired
- sessionStorage otherwise

## Cookie policy

`CookieHelper` determines `SameSite` and `Secure` based on environment and request context.
For cross-site frontend/backend deployments, use:
- HTTPS
- `COOKIE_SAMESITE=None`
- matching CORS origin configuration

## Environment Variables

### Backend `.env` (example)

```env
DB_DRIVER=pgsql
DB_SSLMODE=require

# Option A: explicit connection fields (commonly Supabase)
DB_HOST=aws-1-ap-northeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF
DB_PASSWORD=YOUR_SUPABASE_DB_PASSWORD

# Option B: URL style (commonly Railway)
DATABASE_URL=postgresql://postgres:YOUR_PASSWORD@HOST:5432/railway
DATABASE_PUBLIC_URL=postgresql://postgres:YOUR_PASSWORD@HOST:PORT/railway

JWT_SECRET=YOUR_JWT_SECRET

CORS_ALLOWED_ORIGINS=https://your-frontend-domain.vercel.app
CORS_ALLOW_VERCEL_PREVIEWS=true

COOKIE_SAMESITE=None
COOKIE_DOMAIN=
COOKIE_PATH=/

MAIL_ENABLED=true
MAIL_PROVIDER=auto
MAIL_TIMEOUT=20
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME=E-Bookstore
PURCHASE_ALERT_TO=owner@example.com
PURCHASE_ALERT_SUBJECT_PREFIX=[New Purchase]

# Recommended provider transport
RESEND_API_KEY=re_xxxxxxxxxxxxx
RESEND_API_URL=https://api.resend.com/emails

# Optional SMTP fallback
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_pass
```

### Frontend `frontend/.env` (example)

```env
VITE_API_BASE_URL=http://localhost/Ecommerce/public
```

Notes:
- If this variable is omitted in dev, frontend currently falls back to `http://localhost/Ecommerce/public`.
- For production deployments, always set explicit `VITE_API_BASE_URL`.

## Local Development Runbook

1. Install backend dependencies:

```bash
composer install
```

2. Backend run options:
- XAMPP/Apache style: serve from `http://localhost/Ecommerce/public`
- Built-in PHP server style:

```bash
php -S localhost:3000 -t public
```

If you use port 3000, set frontend env accordingly:

```env
VITE_API_BASE_URL=http://localhost:3000
```

3. Install and run frontend:

```bash
cd frontend
npm install
npm run dev
```

4. Build check:

```bash
npm run build
```

## Database Data Model And Persistence Strategy

## Why cart/orders are in JSONB

This project stores user cart and order history in JSONB columns:
- `customers.order_history`
- `admins.processed_orders`

Normalized in-app shape:

```json
{
  "cart": [],
  "orders": []
}
```

The code also supports legacy array-only payloads for backward compatibility.

## Checkout writes

Checkout transaction does:
- stock decrement on each purchased book
- sales counter increment when `sales_count` or `sold` column exists
- prepend order into user `orders` array
- clear user `cart`

After DB commit, checkout enqueues purchase-alert outbox job.
No direct SMTP send occurs in checkout request path.

## API Endpoint Catalog

## Public

- `GET /api/books`
- `GET /api/books/{id}`

## Auth and profile

- `POST /api/login` (preferred unified login)
- `GET /api/auth/profile`
- `POST /api/logout`
- `POST /api/customers/post` (customer signup)
- `POST /api/customers/login` (legacy)
- `POST /api/admins/login` (legacy)

## Cart and orders

- `GET /api/cart`
- `POST /api/cart/add`
- `PUT /api/cart/quantity`
- `DELETE /api/cart/remove`
- `POST /api/cart/checkout`
- `GET /api/orders`
- `GET /api/orders/{orderId}`
- `GET /api/admin/dashboard/orders`

## Management endpoints

Protected by auth middleware:
- Books: `POST /api/books/post`, `PUT /api/books/put`, `DELETE /api/books/delete`, `PUT /api/books/stock`
- Authors: `GET /api/authors`, `POST /api/authors/post`, `PUT /api/authors/put`, `DELETE /api/authors/delete`
- Categories: `GET /api/categories`, `POST /api/categories/post`, `PUT /api/categories/put`, `DELETE /api/categories/delete`
- Customers: `GET /api/customers`, `PUT /api/customers/put`, `DELETE /api/customers/delete`
- Admins: `GET /api/admins`, `PUT /api/admins/put`, `DELETE /api/admins/delete`

## Frontend Routes

Defined in `frontend/src/App.jsx`:
- `/` Homepage
- `/login`
- `/signup`
- `/product/:bookId`
- `/cart`
- `/checkout`
- `/orders`
- `/orders/:orderId`
- `/admin/dashboard` (admin-only)
- `/dashboard` (admin-only alias)

## End-To-End Flows

## Flow 1: Signup

1. User submits signup form (`frontend/src/pages/Auth/Signup/index.jsx`).
2. Frontend calls `POST /api/customers/post`.
3. Backend validates payload and persists customer record.
4. Password is hashed before storing.
5. Frontend shows result and routes user to login flow.

## Flow 2: Login

1. User submits login form (`frontend/src/pages/Auth/Login/index.jsx`).
2. Frontend calls `POST /api/login`.
3. Backend verifies credentials against customer/admin.
4. Backend issues JWT + sets `auth_token` cookie.
5. Frontend stores token and redirects by role:
- admin -> `/admin/dashboard`
- customer -> `/`

## Flow 3: Browse books and profile-aware nav

1. Homepage loads (`frontend/src/pages/Home/Homepage/index.jsx`).
2. Navbar calls `GET /api/auth/profile`.
3. Homepage calls `GET /api/books`.
4. UI renders searchable/filterable products.
5. Navbar adapts actions by role (admin sees Dashboard/Home links).

## Flow 4: Cart add/update/remove

1. Add to cart triggers `POST /api/cart/add`.
2. Cart page loads current cart with `GET /api/cart`.
3. Item edits are maintained in local UI state.
4. Quantity sync occurs via `PUT /api/cart/quantity` when needed.
5. Remove uses `DELETE /api/cart/remove`.

## Flow 5: Checkout and outbox enqueue

1. Frontend posts checkout details to `POST /api/cart/checkout`.
2. Backend validates cart and stock.
3. Transaction executes stock/sales/order/cart updates.
4. Transaction commits.
5. Backend inserts `purchase_alert_outbox` job.
6. Response includes:
- order payload
- `mail_status` (`queued` or `queue_failed`)
- `mail_queue_id` when available

Result: checkout latency is decoupled from email provider latency.

## Flow 6: Orders and detail page

1. Orders list calls `GET /api/orders`.
2. User clicks order -> `/orders/:orderId`.
3. Detail page calls `GET /api/orders/{orderId}`.
4. Backend lookup supports both:
- internal `id`
- display `orderNumber` (`ORD-...`)
5. Admin can resolve details across all users if not found in own store.

## Flow 7: Admin dashboard data

`frontend/src/pages/Dashboard/index.jsx` loads data from:
- `/api/auth/profile`
- `/api/books`
- `/api/orders`
- `/api/admin/dashboard/orders`
- `/api/authors`
- `/api/categories`
- `/api/customers`

Dashboard sections include:
- KPI cards
- Revenue and breakdown charts
- Pattern candle chart
- All recent orders
- Admin recent orders
- Low stock
- Management center (customers/books/categories/authors)

## Flow 8: Low stock restock (important)

Current robust flow:

1. Low stock list is derived using `stock <= 10`, so out-of-stock (`0`) books are included.
2. Admin enters per-row restock quantity.
3. Frontend validates quantity `> 0`.
4. Frontend calls `PUT /api/books/stock` with:

```json
{
  "id": 123,
  "quantity": 10
}
```

5. Backend `BookController::restockBook()` validates input.
6. Repository `incrementStock()` executes:
- `UPDATE books SET stock = stock + :quantity WHERE id = :id`
7. Frontend refreshes books and updates Low Stock panel.

Why this endpoint exists:
- `PUT /api/books/put` requires full book payload and can fail for incomplete/legacy records.
- `PUT /api/books/stock` is minimal and reliable for restock-only actions.

## Email Queue Architecture

## Outbox table purpose

`purchase_alert_outbox` stores durable email jobs independent of request lifecycle.

Core fields:
- `status` (`pending`, `sending`, `sent`, `failed`)
- `attempt_count`
- `next_attempt_at`
- `last_error`
- `payload` JSONB with order + context

## Worker behavior

Script: `scripts/process_purchase_alert_queue.php`

Algorithm:
1. Claim due jobs with `FOR UPDATE SKIP LOCKED`.
2. Move claimed jobs to `sending`.
3. Send email via `MailHelper::sendPurchaseAlert()`.
4. On success -> mark `sent` and set `sent_at`.
5. On failure -> retry with exponential backoff.
6. After max attempts -> mark `failed`.

Supported args:
- `--limit` (default `20`)
- `--max-attempts` (default `6`)

Recommended Railway command:

```bash
php -d variables_order=EGPCS scripts/process_purchase_alert_queue.php --limit=20 --max-attempts=6
```

## Queue stats script

Run:

```bash
php scripts/purchase_alert_queue_stats.php
```

Returns JSON with:
- pending/sending/failed/sent counts
- oldest pending age seconds

## Admin Dashboard Functional Responsibilities

`frontend/src/pages/Dashboard/index.jsx` handles multiple responsibilities:

1. Role-protected analytics UI
- Uses `AdminRoute` to enforce admin-only access

2. KPI and chart calculations
- Revenue, orders, customers, books
- period switching (`today`, `week`, `month`, `year`)
- chart animation replay on period changes
- viewport-triggered animation start for heavy sections

3. Section navigation
- in-page links for major sections
- scroll offset handling for sticky headers

4. Recent orders and low stock
- all-users and admin-only recent order lists
- deep links to order detail pages
- low stock restock action with loading state

5. Management center CRUD
- customers (edit/delete, add disabled by product rule)
- books (add/edit/delete)
- categories (add/edit/delete)
- authors (add/edit/delete)

6. Management center list UX
- shared search/filter component (`BookSearchControls`) reused across:
  - Homepage book grid
  - Dashboard customers/books/categories/authors lists
- per-tab scoped search:
  - Customers: name/email/phone
  - Books: title/author/category
  - Categories: name
  - Authors: name/bio
- highlighted text matches in list rows for faster visual scanning
- search/filter controls pinned above the scroll area (list content scrolls independently)

7. Management center action UX
- delete confirmation uses SweetAlert (replaces browser `window.confirm`)
- phone behavior:
  - tapping `Edit` (and `Add` where available) opens form in a centered modal
  - modal is fixed to viewport center, independent of page scroll position
  - modal content is internally scrollable (`max-height + overflow-y-auto`)
  - backdrop/cancel/close actions dismiss popup cleanly

## Navbar And Shared UX Behavior

`frontend/src/components/StoreNavbar.jsx`:
- shared on major pages
- responsive for phone/tablet/desktop
- profile dropdown and logout modal
- conditional admin quick links
- optional reusable back button via props:
- `backTo`
- `backLabel`

This component centralizes navigation consistency across screens.

## Operational SQL Snippets

## Inspect outbox recent rows

```sql
select id, order_number, status, attempt_count, created_at, sent_at, last_error
from purchase_alert_outbox
order by id desc
limit 20;
```

## Requeue failed jobs after fixing config

```sql
update purchase_alert_outbox
set status = 'pending',
    next_attempt_at = now(),
    updated_at = now()
where status = 'failed';
```

## Quick queue health view

```sql
select
  count(*) filter (where status = 'pending') as pending_count,
  count(*) filter (where status = 'sending') as sending_count,
  count(*) filter (where status = 'failed') as failed_count,
  count(*) filter (where status = 'sent') as sent_count
from purchase_alert_outbox;
```

## Deployment Notes (Railway + Supabase)

- Supabase and Railway are DB/platform providers, not replacements for backend API code.
- Backend and worker must point to the same DB if they share outbox.
- If frontend is newer than backend, new endpoint calls (for example `/api/books/stock`) can fail with `404`.
- Always deploy backend first when introducing new API routes.

Recommended rollout order for API changes:
1. Deploy backend.
2. Run/verify required SQL migrations.
3. Deploy frontend.
4. Run smoke tests (login, cart, checkout, orders, admin dashboard, low stock restock).

## Troubleshooting Guide

## Auth and session

Problem: repeated `401` on protected routes
- Confirm `JWT_SECRET` consistency.
- Confirm cookie settings for deployment model (`SameSite=None`, HTTPS).
- Confirm frontend sends `credentials: include` (already done in `apiFetch`).

Problem: admin page redirects to login/home unexpectedly
- Check `GET /api/auth/profile` response role.
- Verify `AdminRoute` role parsing and token validity.

## API connectivity

Problem: frontend hits wrong host
- Verify `VITE_API_BASE_URL` in deployment.
- In dev, note fallback is `http://localhost/Ecommerce/public`.

Problem: route returns 404
- Ensure backend deployment includes latest `routes/web.php` changes.
- Verify path exactly matches expected endpoint.

## Cart and checkout

Problem: checkout slow
- Ensure direct SMTP is not used in request path.
- Verify outbox enqueue is working and worker handles send asynchronously.

Problem: order detail not found for `ORD-...`
- Ensure backend supports lookup by both internal id and `orderNumber`.
- Ensure production backend is up to date.

## Low stock restock

Problem: out-of-stock books not visible
- Confirm frontend filter uses `stock <= 10`.

Problem: restock fails for some books
- Confirm frontend calls `/api/books/stock`, not `/api/books/put`.
- Confirm backend route/controller/repository changes are deployed.

## Email queue and delivery

Problem: rows stay pending
- Worker may not be running or not on correct schedule.
- Verify Railway cron service, schedule, and command.
- Verify worker and API share DB credentials.

Problem: repeated `SMTP Error: Could not connect to SMTP host`
- Prefer Resend API transport using `RESEND_API_KEY`.
- SMTP from cloud containers is often unreliable due network/provider restrictions.

Problem: worker logs show no pending but DB shows pending
- Usually worker points to a different database than the one inspected.
- Compare connection env vars for API service, worker service, and SQL console.

## Validation Checklist After Any Release

1. Can customer login and stay authenticated?
2. Can admin login and open dashboard?
3. Can books load on homepage?
4. Can cart add/update/remove work?
5. Can checkout complete quickly?
6. Does checkout response include `mail_status`?
7. Do new outbox rows appear?
8. Does worker process rows to `sent` or retry them?
9. Can `/orders` and `/orders/:orderId` resolve recent orders?
10. Can Low Stock show `0` stock books and restock them?

## Notes

- Legacy endpoints are retained for compatibility.
- Unified `/api/login` is preferred for frontend usage.
- Current design intentionally prioritizes checkout reliability over synchronous email sending.
