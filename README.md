# Book E-commerce Application

This is a custom-built PHP application for a book e-commerce store.

## Installation

1. Clone the repository.
2. Run `composer install`.
3. Configure `.env` with your database credentials.
4. Configure your web server to point to the `public` directory.

## Supabase Database Setup

Use this configuration in `.env`:

```env
DB_DRIVER=pgsql
DB_HOST=aws-1-ap-northeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.hpxsnvwvwyzydpryjyni
DB_PASSWORD=YOUR_SUPABASE_DB_PASSWORD
DB_SSLMODE=require
```

Note:
- Replace `YOUR_SUPABASE_DB_PASSWORD` with the real Supabase DB password.

## Railway Database Setup (Alternative)

Backend now supports Railway URLs directly via:
- `DATABASE_URL` (private/internal)
- `DATABASE_PUBLIC_URL` (public TCP proxy)

Use `.env.railway.example` as a template.

Recommended:
- If API is hosted on Railway, use `DATABASE_URL` (private domain) for best speed.
- If running API locally, use `DATABASE_PUBLIC_URL` (TCP proxy host/port).

## Usage

- Frontend:

```bash
cd frontend
npm run dev
```

- Backend:

```bash
php -S localhost:3000 -t public
```

## Purchase Alert Email (Owner Notification)

On every successful `POST /api/cart/checkout`, the backend can send an owner alert email.

Behavior:
- Checkout enqueues purchase alerts into `purchase_alert_outbox` (non-blocking).
- Worker script sends queued emails asynchronously.
- If sending fails, worker retries with backoff and eventually marks job as `failed`.
- If `MAIL_ENABLED=false` or `PURCHASE_ALERT_TO` is empty, worker skips send.

Required env keys:

```env
MAIL_ENABLED=true
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME=Ecommerce Store
PURCHASE_ALERT_TO=owner@example.com
PURCHASE_ALERT_SUBJECT_PREFIX=[New Purchase]
```

Queue migration:

```bash
# run in PostgreSQL (local + Railway)
database/purchase_alert_outbox.sql
```

Queue worker command:

```bash
php scripts/process_purchase_alert_queue.php --limit=20 --max-attempts=6
```

Queue stats command:

```bash
php scripts/purchase_alert_queue_stats.php
```

Railway cron recommendation:
- Command: `php scripts/process_purchase_alert_queue.php --limit=20`
- Frequency: every minute

## Purchase Alert Test Checklist

1. Place a checkout with valid SMTP credentials:
- Expect successful order response.
- Expect `mail_status` = `queued` in checkout response.
- Expect one row in outbox with `status='pending'` or quickly `status='sent'`.

2. Break SMTP credentials:
- Expect successful order response.
- Expect no rollback of order/cart state.
- Expect worker retries and then `status='failed'` with `last_error`.

3. Set `MAIL_ENABLED=false`:
- Expect successful order response.
- Expect outbox job processed without outbound email.

4. Keep `MAIL_ENABLED=true` but clear `PURCHASE_ALERT_TO`:
- Expect successful order response.
- Expect alert skip log in worker and no delivered email.

## Fly.io Deployment

1. Login to Fly:

```bash
fly auth login
```

2. Create app once (if not created yet):

```bash
fly launch --no-deploy
```

3. Set backend secrets:

```bash
fly secrets set DB_DRIVER=pgsql DB_HOST=aws-1-ap-northeast-1.pooler.supabase.com DB_PORT=5432 DB_DATABASE=postgres DB_USERNAME=postgres.hpxsnvwvwyzydpryjyni DB_PASSWORD=YOUR_SUPABASE_PASSWORD DB_SSLMODE=require JWT_SECRET=YOUR_JWT_SECRET CORS_ALLOWED_ORIGINS=https://your-frontend-domain.vercel.app CORS_ALLOW_VERCEL_PREVIEWS=true COOKIE_SAMESITE=None
```

4. Deploy:

```bash
fly deploy
```

5. Use your Fly URL as frontend API base URL:

```env
VITE_API_BASE_URL=https://your-app-name.fly.dev
```
