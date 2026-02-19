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

## Frontend Routes

- `/` -> Homepage
- `/login` -> Login page
- `/signup` -> Signup page
- `/cart` -> Cart page
- `/checkout` -> Checkout page
- `/orders` -> Orders list
- `/orders/:orderId` -> Order detail page

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
