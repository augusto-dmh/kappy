# Modular Monolith Conventions

This application uses a modular-monolith architecture with [`internachi/modular`](https://github.com/InterNachi/modular) (v3). Domain modules live in `app-modules/` and maintain clear boundaries while sharing a common Laravel core.

## Overview

**Shared core** (`app/`): Authentication (Fortify), settings, and cross-module utilities.

**Modules** (`app-modules/*/`): Domain-specific features — one module per bounded context. Live modules: `identity`, `github-app`, `review`.

**Frontend convention**: The `module::page` naming scheme maps server-side routes to co-located React pages. A controller returns `Inertia::render('review::index')`, which resolves to `app-modules/review/resources/js/pages/index.tsx`.

---

## Creating a Module

Modules are scaffolded with `php artisan make:module`, then symlinked into the Composer autoloader.

```bash
php artisan make:module YourModule --accept-default-namespace
composer update modules/yourmodule
```

**Critical:** The `--accept-default-namespace` flag auto-accepts the namespace prompt. Without it, the command pauses for user input and fails in non-interactive environments.

**What this does:**
- Creates `app-modules/yourmodule/` with skeleton files.
- Adds a `path` repository to `composer.json` pointing to `app-modules/yourmodule`.
- Adds `"modules/yourmodule": "*"` to `require` (local path symlink).
- Registers the module's service provider via `composer.json` `extra.laravel.providers` (auto-discovered; no edit to `bootstrap/providers.php`).
- Runs `composer update` to symlink the path repo and resolve the `Modules\YourModule\` PSR-4 namespace.

---

## Module Directory Layout

```
app-modules/<name>/
├── composer.json                       # Module metadata, PSR-4 namespace
├── src/
│   ├── Providers/<Name>ServiceProvider.php    # Auto-discovered service provider
│   ├── Models/
│   ├── Http/
│   │   └── Controllers/
│   └── ...
├── routes/<name>-routes.php            # Auto-loaded by internachi/modular
├── database/
│   ├── migrations/                     # Run with standard `php artisan migrate`
│   ├── factories/
│   └── seeders/
├── resources/
│   └── js/pages/                       # Co-located Inertia React pages
└── tests/
    └── Feature/
```

### Key conventions:

- **Namespace**: `Modules\<Name>\` (auto-configured in `composer.json`).
- **Routes file**: `routes/<name>-routes.php`. Must explicitly wrap routes in `['web']` middleware group. Exception: server-to-server endpoints (e.g. webhook receivers) are declared OUTSIDE `['web']` on purpose, so they carry no session/CSRF/Inertia middleware — see `github-app/routes/github-app-routes.php`.
- **Models**: Live in `src/Models/`.
- **Controllers**: Live in `src/Http/Controllers/`.
- **Tests**: Discovery via PHPUnit `Modules` testsuite; place tests in `tests/Feature/`.
- **Inertia pages**: React components at `resources/js/pages/<page>.tsx` (lowercase filenames).

---

## Adding Backend Pieces

### Create a model with migration and factory:

```bash
php artisan make:model Order --module=orders -mf
```

This creates:
- `app-modules/orders/src/Models/Order.php`
- `app-modules/orders/database/migrations/YYYY_MM_DD_HHMMSS_create_orders_table.php`
- `app-modules/orders/database/factories/OrderFactory.php`

Run migrations with the standard command:

```bash
php artisan migrate
```

### Create a controller:

```bash
php artisan make:controller OrderController --module=orders
```

Creates `app-modules/orders/src/Http/Controllers/OrderController.php`.

### Route and render an Inertia page:

Edit `app-modules/<name>/routes/<name>-routes.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderController;

Route::middleware(['web'])->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});
```

In the controller, use the `module::page` naming convention:

```php
<?php

namespace Modules\Orders\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Orders\Models\Order;

class OrderController
{
    public function index(): Response
    {
        return Inertia::render('orders::index', [
            'orders' => Order::query()->latest()->get(),
        ]);
    }
}
```

The component name `orders::index` is split and mapped to `app-modules/orders/resources/js/pages/index.tsx`. The live review inbox uses the same pattern: `review::index` → `app-modules/review/resources/js/pages/index.tsx`.

---

## Adding a Frontend Page

### Page location:

```
app-modules/<module>/resources/js/pages/<page>.tsx
```

Use **lowercase filenames** to match the existing `resources/js/pages/` convention.

### The `module::page` convention:

The controller passes a string like `'review::index'` to `Inertia::render()`. This is resolved by `resources/js/app.tsx`:

```typescript
resolve: (name) => {
    if (name.includes('::')) {
        const [module, page] = name.split('::'); // 'review::index'
        const path = `../../app-modules/${module}/resources/js/pages/${page}.tsx`;
        const loader = modulePages[path];
        if (!loader) {
            throw new Error('Inertia module page not found: ' + path);
        }
        return loader() as Promise<ResolvedComponent>;
    }
    // Root pages (no ::) resolve from resources/js/pages/
    const path = `./pages/${name}.tsx`;
    const loader = rootPages[path];
    if (!loader) {
        throw new Error('Inertia page not found: ' + path);
    }
    return loader() as Promise<ResolvedComponent>;
},
```

Similarly, `resources/views/app.blade.php` computes the Vite preload entry:

```blade
@php
    $pageEntry = str_contains($page['component'], '::')
        ? 'app-modules/'.str_replace('::', '/resources/js/pages/', $page['component']).'.tsx'
        : "resources/js/pages/{$page['component']}.tsx";
@endphp
@vite(['resources/css/app.css', 'resources/js/app.tsx', $pageEntry])
```

**Sync point:** If you change the page-path convention in `resources/js/app.tsx`, you MUST update the same path logic in `resources/views/app.blade.php`. Mismatched paths cause Vite manifest errors (500).

### Import the shared layer:

The single global shared layer is `resources/js/` (`components/ui`, `hooks`, `lib`, `layouts`, `types`). Import it via the `@/` alias from any page or module:

```typescript
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
```

A module page can also set a layout callback:

```typescript
import { index } from '@/routes/reviews';

export default function ReviewsIndex({ reviews }: Props) {
    return (/* ... */);
}

ReviewsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reviews',
            href: index(),
        },
    ],
};
```

The global layout callback in `resources/js/app.tsx` applies layouts based on page name:

```typescript
layout: (name) => {
    switch (true) {
        case name === 'welcome':
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
},
```

Module pages default to `AppLayout` unless the name matches a special pattern.

---

## Import Boundaries

Module boundaries are enforced by ESLint via `eslint-plugin-boundaries`.

### Rules (unidirectional):

- ✅ A module **may** import the shared layer (`resources/js/`, `@/` alias) and its own module.
- ✅ The shared layer **may** import nothing except shared utilities (no modules).
- ❌ A module **must NOT** import a different module.

Violating a boundary triggers an ESLint error:

```bash
npm run lint
```

---

## Styling

Tailwind v4 is configured to scan module pages for utility classes:

```css
/* resources/css/app.css */
@source '../../app-modules';
```

This prevents utilities used only in module pages from being purged. New modules are automatically scanned; no per-module configuration needed.

---

## Testing a Module

### Test location:

```
app-modules/<name>/tests/Feature/<Feature>Test.php
```

### Test discovery:

PHPUnit discovers module tests via the `Modules` testsuite in `phpunit.xml`:

```xml
<testsuite name="Modules">
    <directory suffix="Test.php">./app-modules/*/tests</directory>
</testsuite>
```

All module tests extend `Tests\TestCase` and use `RefreshDatabase` via `tests/Pest.php`:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app-modules');
```

### Asserting Inertia components:

When asserting a module page, use the `module::page` component name:

```php
<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the review inbox renders the co-located page', function () {
    $this->actingAs($user)
        ->get(route('reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review::index')
            ->has('reviews')
        );
});
```

`config/inertia.php` auto-discovers module page directories via glob:

```php
'pages' => [
    'paths' => [
        resource_path('js/pages'),
        ...(glob(base_path('app-modules/*/resources/js/pages')) ?: []),
    ],
],
```

New modules are picked up automatically. The `App\Providers\AppServiceProvider` rebinds `inertia.view-finder` as a namespace-aware singleton so assertions work with `ensure_pages_exist=true`.

### Running tests:

Run all tests:

```bash
php artisan test
```

Run module tests only:

```bash
php artisan test --testsuite=Modules
```

Run a specific test file:

```bash
php artisan test --compact app-modules/review/tests/Feature/ReviewControllerTest.php
```

---

## Worked Example: Scaffold an `Orders` Module

Chain the full scaffolding flow:

```bash
# 1. Create the module
php artisan make:module Orders --accept-default-namespace

# 2. Symlink to Composer
composer update modules/orders

# 3. Create an Order model with migration and factory
php artisan make:model Order --module=orders -mf

# 4. Create a controller
php artisan make:controller OrderController --module=orders

# 5. Add routes
cat > app-modules/orders/routes/orders-routes.php << 'EOF'
<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderController;

Route::middleware(['web'])->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});
EOF

# 6. Add the controller logic
cat > app-modules/orders/src/Http/Controllers/OrderController.php << 'EOF'
<?php

namespace Modules\Orders\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Orders\Models\Order;

class OrderController
{
    public function index(): Response
    {
        return Inertia::render('orders::index', [
            'orders' => Order::query()->latest()->get(),
        ]);
    }
}
EOF

# 7. Create the React page
mkdir -p app-modules/orders/resources/js/pages
cat > app-modules/orders/resources/js/pages/index.tsx << 'EOF'
import { Head } from '@inertiajs/react';

type Order = {
    id: number;
    total: number;
    created_at: string;
};

type Props = {
    orders: Order[];
};

export default function OrdersIndex({ orders }: Props) {
    return (
        <>
            <Head title="Orders" />
            <div className="p-4">
                <h1 className="text-2xl font-bold">Orders</h1>
                {orders.length === 0 ? (
                    <p>No orders.</p>
                ) : (
                    <ul>
                        {orders.map((order) => (
                            <li key={order.id}>Order #{order.id} - ${(order.total / 100).toFixed(2)}</li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Orders',
            href: '/orders',
        },
    ],
};
EOF

# 8. Run migrations
php artisan migrate

# 9. Test the module
php artisan test --testsuite=Modules

# 10. Check linting
npm run lint
```

After these steps, visit `/orders` to see the page render with data.

---

## Key Takeaways

1. **Two-step scaffold**: `make:module` → `composer update modules/<name>`.
2. **Routes**: Explicitly wrap in `['web']` middleware.
3. **Inertia**: Use `module::page` naming; pages live at `app-modules/<module>/resources/js/pages/<page>.tsx`.
4. **Sync point**: `resources/js/app.tsx` and `resources/views/app.blade.php` must both compute `module::page` paths identically.
5. **Boundaries**: Shared layer only; modules cannot import each other. ESLint enforces this.
6. **Testing**: Module tests auto-discovered; use `component('module::page')` in assertions.
7. **Styling**: Tailwind auto-scans module pages via `@source '../../app-modules'`.
