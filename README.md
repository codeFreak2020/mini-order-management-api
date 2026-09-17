# Mini Order Management API

A small backend system where users can **register / login**, manage a **product catalogue**, and **place orders** through REST APIs.

Built with **Laravel 12**, **PHP 8.3**, and **MySQL**, following MVC, Eloquent, Form Requests, API Resources, migrations with foreign keys, and seeders.

---

## Table of contents

1. [Features](#features)
2. [Tech stack](#tech-stack)
3. [Requirements](#requirements)
4. [Local setup](#local-setup)
5. [Docker setup (bonus)](#docker-setup-bonus)
6. [API endpoints](#api-endpoints)
7. [Business logic — placing an order](#business-logic--placing-an-order)
8. [R&D features explained](#rd-features-explained)
9. [Testing](#testing)
10. [API documentation (Swagger)](#api-documentation-swagger)
11. [Project structure](#project-structure)
12. [Database schema](#database-schema)

---

## Features

- **Authentication** (JWT via `php-open-source-saver/jwt-auth`): register, login with email/phone/username, token refresh, logout.
- **Product management**: full CRUD with search filters, pagination, caching and soft deletes.
- **Order system**: place an order, list own orders, view order details.
- **Business logic**: stock check → stock reduction → total calculation → order items, all atomic.
- **R&D**: Redis caching, API rate limiting, queued order processing, email notification, product search filters.
- **Quality**: Form Request validation, API Resources, centralized JSON error handling, unit/feature tests.
- **Bonus**: Docker, PHPUnit tests, Swagger/OpenAPI docs, Postman collection.

---

## Tech stack

| Layer      | Technology                                   |
| ---------- | -------------------------------------------- |
| Backend    | Laravel 12 (PHP 8.2+)                        |
| Database   | MySQL 8                                      |
| Auth       | JWT (`php-open-source-saver/jwt-auth`)        |
| Cache      | Redis (via `predis`, fallback: `database`)   |
| Queue      | Database / Redis                             |
| Mail       | Laravel notifications (log / SMTP)           |
| Docs       | OpenAPI 3 + Swagger UI                       |
| Tests      | PHPUnit                                      |

---

## Requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `curl` extensions
- Composer **2.x**
- MySQL **8+**
- (Optional) Redis **7+** for cache/queue
- (Optional) Docker + Docker Compose

---

## Local setup

```bash
# 1. Install dependencies
composer install

# 2. Create the environment file
cp .env.example .env

# 3. Generate the application key
php artisan key:generate

# 4. Create a MySQL database (e.g. `order_api`) and update .env:
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=order_api
#    DB_USERNAME=root
#    DB_PASSWORD=

# 5. Run migrations and seed sample data
php artisan migrate --seed

# 6. Start the server
php artisan serve

# 7. (Optional) start a queue worker so order emails are processed
php artisan queue:work
```

The API is now available at `http://localhost:8000/api`.

> **Seeded demo user:** `demo@example.com` / `password`

---

## Docker setup (bonus)

A full stack (`php-fpm` + `nginx` + `mysql` + `redis` + a queue worker) is provided via
`docker-compose.yml` and `Dockerfile`.

```bash
docker compose up -d --build

# Run migrations + seeders inside the app container
docker compose exec app php artisan migrate --seed

# Optional: run tests in the container
docker compose exec app php artisan test
```

The API is exposed at `http://localhost:8080/api` (nginx → php-fpm).

| Service  | Address             |
| -------- | ------------------- |
| API      | `http://localhost:8080` |
| MySQL    | `127.0.0.1:33061`   |
| Redis    | `127.0.0.1:63791`   |

---

## API endpoints

Base URL: `http://localhost:8000/api`

### Authentication

| Method | Endpoint            | Auth | Description                                              |
| ------ | ------------------- | ---- | -------------------------------------------------------- |
| POST   | `/auth/register`    | No   | Register a new user (name, email, phone, username, password) |
| POST   | `/auth/login`       | No   | Login with email, phone, or username → access + refresh tokens |
| POST   | `/auth/refresh`     | No   | Exchange a refresh token for a new token pair            |
| POST   | `/auth/logout`      | Yes  | Invalidate the current token                             |

### Products

| Method   | Endpoint          | Auth | Description                          |
| -------- | ----------------- | ---- | ------------------------------------ |
| GET      | `/products`       | Yes  | List products (filters + pagination) |
| GET      | `/products/{id}`  | Yes  | Get a single product (cached)        |
| POST     | `/products`       | Yes  | Create a product                     |
| PUT/PATCH| `/products/{id}`  | Yes  | Update a product                     |
| DELETE   | `/products/{id}`  | Yes  | Soft-delete a product                |

### Orders

| Method | Endpoint         | Auth | Description                          |
| ------ | ---------------- | ---- | ------------------------------------ |
| POST   | `/orders`        | Yes  | Create an order                      |
| GET    | `/orders`        | Yes  | List the authenticated user's orders |
| GET    | `/orders/{id}`   | Yes  | Get order details (owner only)       |

### Product list query parameters

| Param       | Description                                  | Example                        |
| ----------- | -------------------------------------------- | ------------------------------ |
| `search`    | Search in name, SKU, description              | `search=mouse`                 |
| `min_price` | Minimum price                                 | `min_price=10`                 |
| `max_price` | Maximum price                                 | `max_price=100`                |
| `in_stock`  | Only products with stock > 0 (`1`/`0`)        | `in_stock=1`                   |
| `sort_by`   | `name`, `price`, `stock`, `created_at`        | `sort_by=price`                |
| `sort_dir`  | `asc` / `desc`                                | `sort_dir=asc`                 |
| `per_page`  | Items per page (1–100, default 15)            | `per_page=50`                  |
| `page`      | Page number                                  | `page=2`                       |

### Authentication

Send the token returned by register/login as a bearer token:

```
Authorization: Bearer <token>
```

### Response envelope

Successful responses use:

```json
{
  "success": true,
  "message": "…",
  "data": { "…": "…" }
}
```

Errors use:

```json
{
  "success": false,
  "message": "…",
  "errors": { "field": ["message"] }
}
```

> Prices are returned as strings with two decimals (e.g. `"20.00"`) to avoid floating-point rounding.

---

## Business logic — placing an order

`POST /api/orders` runs through `App\Services\OrderService::placeOrder()`. The whole flow is wrapped in a **single database transaction** so the system stays consistent:

1. **Validate** the payload (Form Request `StoreOrderRequest`): items must be a non-empty array of `product_id` + `quantity`.
2. **Lock stock rows** (`SELECT ... FOR UPDATE`) to prevent race conditions / overselling.
3. **Check stock** for every product. If insufficient, an `InsufficientStockException` is thrown → HTTP `422`.
4. **Reduce stock** (`decrement`) for each purchased quantity.
5. **Calculate total price** = `Σ (quantity × unit_price)`.
6. **Save the order** and its **order items** (with product-name/price snapshots so order history survives product deletion).
7. **Commit**, then dispatch `ProcessOrderJob` to a queue (finalise the order to `completed` + send the confirmation email).

If any step fails, the transaction rolls back — no partial orders or phantom stock changes.

---

## R&D features explained

### 1. Redis caching for products

- Single-product lookups (`GET /api/products/{id}`) are cached via `Cache::remember("products:{id}", ttl, …)`.
- Cache is invalidated on product **update** and **delete** (`Cache::forget`).
- TTL is configurable with `PRODUCT_CACHE_TTL` (default `300` seconds).
- The store is driver-agnostic: set `CACHE_STORE=redis` to use Redis, or `CACHE_STORE=database` for a zero-infrastructure fallback.
- **Why:** `GET /products/{id}` is the most frequent read; caching avoids a DB round-trip per request.

### 2. API rate limiting

- The `api` middleware group applies `throttle:api` → **60 requests/minute** per user (or per IP when anonymous).
- Auth endpoints get a stricter `throttle:auth` → **5 requests/minute** per IP (brute-force protection).
- Limits are defined in `App\Providers\AppServiceProvider` via `RateLimiter::for(...)`.

### 3. Queue for order processing

- The synchronous part of the order (stock check/reduce/total/save) must be immediate for correctness.
- The slower "post-processing" (`App\Jobs\ProcessOrderJob`) is dispatched to the queue **after commit**:
  1. marks the order as `completed`;
  2. sends the confirmation email.
- Default queue connection is `database` (no extra service needed); set `QUEUE_CONNECTION=redis` to use Redis.
- Run `php artisan queue:work` to process the queue.

### 4. Email notification after order

- `App\Notifications\OrderPlaced` (a `ShouldQueue` notification) sends a confirmation email with the order number, total and status.
- It is sent from the queued job after the order is finalised.
- In development `MAIL_MAILER=log` writes the email to `storage/logs/laravel.log`; set SMTP credentials in `.env` for real delivery.

### 5. Product search filters

- `GET /api/products` supports `search`, `min_price`, `max_price`, `in_stock`, `sort_by`, `sort_dir` and pagination.
- `search` uses a `LIKE` query across `name`, `sku` and `description` (indexed `name` column for performance).
- **Production note:** for large datasets, consider MySQL full-text indexes or a search engine such as Meilisearch/Elasticsearch.

### 6. JWT access + refresh tokens

- Login/registration return a **short-lived access token** (`jwt.ttl`, default 60 min) and a **long-lived refresh token** (`jwt.refresh_ttl`, default 2 weeks).
- The refresh token carries a `token_type: refresh` claim; `POST /auth/refresh` validates it and returns a fresh pair.
- Refresh tokens are **rotated**: the old one is blacklisted on use (`jwt.blacklist_enabled`).

### 7. Extensible login identifiers (SOLID)

- Users can log in with `email`, `phone`, or `username` through a single `identifier` field.
- Resolution uses the Strategy pattern: `UserIdentifierResolver` implementations (email/phone/username) are aggregated by `UserIdentifierResolverManager`, so adding a new identifier only requires a new resolver class — no controller changes (Open/Closed Principle).

### 8. Soft deletes for products

- Products use Eloquent `SoftDeletes`: `DELETE /products/{id}` sets `deleted_at` instead of removing the row.
- Soft-deleted products are automatically excluded from listings and lookups, and can be restored or force-deleted later.

---

## Testing

PHPUnit feature tests are located in `tests/Feature` (auth, products, orders). Tests run against an in-memory SQLite database with a sync queue and faked notifications.

```bash
php artisan test
```

Covered scenarios include:

- register / login / logout / invalid credentials / unauthenticated access
- product CRUD, validation, 404s and search filters
- order placement, total calculation, insufficient stock, ownership checks, validation

---

## API documentation (Swagger)

- OpenAPI 3 spec: `public/docs/openapi.yaml`
- Interactive Swagger UI: `http://localhost:8000/api/docs`
- Postman collection: `MiniOrderManagement.postman_collection.json`

---

## Project structure

```
app/
├── Exceptions/InsufficientStockException.php   # Domain exception (stock)
├── Http/
│   ├── Controllers/Api/                        # Auth, Product, Order controllers
│   ├── Requests/                               # Form Request validation
│   └── Resources/                              # API Resources (JSON shaping)
├── Jobs/ProcessOrderJob.php                    # Queued order post-processing
├── Models/                                     # User, Product, Order, OrderItem
├── Notifications/OrderPlaced.php               # Queued email notification
├── Providers/AppServiceProvider.php            # Rate limiters
└── Services/OrderService.php                   # Order business logic
database/
├── factories/                                  # Model factories
├── migrations/                                 # Schema (FKs)
└── seeders/                                    # Sample data
tests/Feature/                                  # PHPUnit feature tests
public/docs/openapi.yaml                        # OpenAPI spec
```

---

## Database schema

```
users               orders                    order_items
─────────           ─────────                 ───────────────
id                  id                        id
name                user_id  ──FK── users     order_id    ──FK── orders
email               order_number (unique)     product_id  ──FK── products (nullable)
phone (unique)      status                    product_name (snapshot)
username (unique)   total_price               quantity
password            …                         unit_price
…                                             subtotal

products
─────────
id
name
sku (unique, nullable)
description
price
stock
deleted_at (soft deletes)
```

All relationships use foreign keys (cascade delete where appropriate). Order items keep a nullable `product_id` plus name/price snapshots, so historical orders remain intact even if a product is later deleted.

---

## License

MIT — created for evaluation purposes.
