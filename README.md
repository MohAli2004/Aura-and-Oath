# Aura & Oath

Premium beauty & personal care e-commerce built with **Laravel 12**, Blade, Tailwind CSS 4, Alpine.js, MySQL/MariaDB, Vite, and PHPUnit.

Brand palette: warm ivory, soft beige, muted blush, taupe, charcoal, subtle gold.  
Typography: **Cormorant Garamond** (display) + **Source Sans 3** (body).

## Stack

| Layer | Choice |
|-------|--------|
| PHP | 8.2+ |
| Framework | Laravel 12 |
| UI | Blade + Tailwind CSS 4 (`@tailwindcss/vite`) |
| Interactivity | Alpine.js (`resources/js/app.js`) |
| DB | MySQL / MariaDB (SQLite in-memory for tests) |
| Assets | Vite 7 |
| Tests | PHPUnit 11 |

> `laravel/pint` is intentionally omitted from `composer.json` (network install issues). Leave it removed unless you add it back locally.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` (see documented `DB_*` and `AURA_*` keys):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auraandoath
DB_USERNAME=root
DB_PASSWORD=

AURA_ADMIN_EMAIL=admin@auraandoath.com
AURA_ADMIN_PASSWORD=password
```

Then:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Dev assets (separate terminal):

```bash
npm run dev
```

Queue workers (notifications are queue-ready):

```bash
php artisan queue:work
```

## Admin credentials (seeded)

| Field | Value |
|-------|-------|
| URL | `/admin` |
| Email | `admin@auraandoath.com` |
| Password | `password` |

**Change this in production** via `AURA_ADMIN_EMAIL` / `AURA_ADMIN_PASSWORD`.

Customer seeds (password `password`): `nour@`, `sara@`, `omar@`, `lina@`, `yasmine@` `@example.com`.

## Stock strategy (`config/aura.php`)

| Event | Inventory effect |
|-------|------------------|
| Order placed | **Reserve** (`reserved_quantity` ↑). Available = stock − reserved |
| Order approved | **Convert** reserved → sold (`stock_quantity` ↓, reserved cleared) |
| Reject / cancel while pending | **Release** reserved |
| Return confirmed resellable | **Restore** stock |

All mutations use DB transactions + `lockForUpdate`. Stock never goes negative.

## Barcodes & SKUs

- Unique **string** barcodes and SKUs on products and variants
- Admin: Inventory scan (Enter submits), Barcode lookup, printable labels
- Duplicate codes rejected by `BarcodeService`

## Key routes

### Storefront
`/`, `/shop`, `/products/{slug}`, `/search`, `/cart`, `/checkout` (auth), `/wishlist`, `/account`, `/account/orders`, `/track-order`, `/about`, `/contact`, `/faq`, `/pages/{slug}`

### Auth
`/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `POST /logout`

### Admin (`auth` + `admin` middleware)
Dashboard · Products (tabbed form + variants JSON) · Categories · Brands · Attributes · Inventory (+ scan) · Orders (approve/reject/status/return) · Invoice & packing slip · Customers · Coupons · Banners · Reports (CSV) · Settings · Audit log · Barcode lookup / labels

## Seeded catalog (approximate)

- 10 categories · 8 brands · 35 products (several with variants)
- Unique EAN-style barcodes · coupons `AURA10`, `OATH10`
- Delivery regions · banners · CMS policy pages · sample orders across statuses

## Architecture notes

- Thin controllers; business logic in `app/Services/`
- Helpers: `money()`, `setting()`, `aura()` via `app/Support/helpers.php` (Composer `files` autoload)
- Guest cart merges on login (`MergeGuestCartOnLogin`)
- Cost prices hidden from customer serialization
- Checkout never trusts client line prices
- Policies: `OrderPolicy`, `ProductPolicy` (+ `Gate::define('admin')`)

## Tests

```bash
php artisan test
# or
php artisan test --filter=AuraCommerceTest
```

Coverage includes: auth, admin protection, product barcode/SKU uniqueness, cart CRUD, guest cart merge, checkout reserve + idempotency, server-side pricing, approve/reject/cancel inventory, return restock, negative stock prevention, IDOR on orders, print invoice/labels.

## Useful commands

```bash
php artisan migrate --seed
php artisan storage:link
php artisan test
npm run build
php artisan serve
php artisan queue:work
```

## Troubleshooting

| Issue | Fix |
|-------|-----|
| `Class not found` / helpers missing | `composer dump-autoload` after install |
| Styles/fonts missing | `npm run build` or `npm run dev` |
| Images 404 in admin uploads | `php artisan storage:link` |
| Notifications not sending | set `QUEUE_CONNECTION` and run `queue:work` |
| Composer network failures | retry later; do not re-add `laravel/pint` unless needed |
