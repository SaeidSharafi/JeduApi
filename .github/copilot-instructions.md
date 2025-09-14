# GitHub Copilot Instructions for JeduShop E-Commerce API

## 1. Core Mandates & Unbreakable Rules

### **API Contract**
- **MUST** use `spatie/laravel-data` for ALL API requests and responses. No exceptions.
- Every controller method **MUST** accept a Data class for requests and return a Data class in responses.
- All Data classes **MUST** be placed in `app/Data/` with proper namespace organization.

### **Business Logic Separation**
- All business logic **MUST** be placed in Action classes within `app/Actions/`.
- Service classes in `app/Services/` are **ONLY** for generic, reusable utilities (payment gateways, SMS, external APIs).
- Controllers **MUST** be thin and delegate to Actions. No business logic in controllers.

### **Authentication Guards**
- Admin endpoints (`/api/v1/admin/*`) **MUST** use `auth:staff` guard with `admin.audit` middleware.
- Customer endpoints (`/api/v1/shop/*`) **MUST** use `auth:user` guard.
- **NEVER** mix guards or use wrong authentication for endpoints.

### **Database Schema**
- All database changes **MUST** be done through migration files in `database/migrations/`.
- Direct database alteration is **FORBIDDEN**.

### **API Responses**
- All controller methods **MUST** return responses using custom response macros from `app/Providers/ResponseMacroServiceProvider.php`.
- Available macros: `response()->success()`, `response()->created()`, `response()->updated()`, `response()->validationErrors()`, `response()->notFound()`, `response()->forbidden()`, `response()->error()`, `response()->unauthorized()`.

### **Authorization**
- Every admin controller method **MUST** include `Gate::authorize()` calls.
- Use appropriate policy methods: `viewAny`, `view`, `create`, `update`, `delete`.

### **Permission System**
- **MUST** use `PermissionEnum` from `app/Enums/PermissionEnum.php` for all permission checks.
- Edit `config/permission-generator.php` to add new permissions, then run `php artisan permission:generate` to regenerate the enum.
- Use `$user->can(PermissionEnum::RESOURCE_ACTION)` in policies (without `->value` for direct enum usage).
- Use `$user->can(PermissionEnum::RESOURCE_ACTION->value)` when you need the string value explicitly.
- Run `php artisan permission:sync` after generating new permissions to update the database.

## 2. Architectural Patterns & "How-To" Guides

### **Pattern: Creating a New Admin API Endpoint**

**Example: `POST /api/v1/admin/products`**

1. **Route Definition** in `routes/Api/V1/admin.php`:
```php
Route::middleware(['auth:staff', 'admin.audit'])->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('product', ProductController::class)
            ->except(['edit', 'create']);
    });
});
```

2. **Request DTO** in `app/Data/Admin/Product/ProductCreateData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductCreateData extends Data
{
    public function __construct(
        public string $name,
        public float $price,
        public int $vendor_id,
        public string $status,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'status' => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
        ];
    }
}
```

3. **Response DTO** in `app/Data/Admin/Product/ProductData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use Spatie\LaravelData\Data;

final class ProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public string $status,
    ) {}
}
```

4. **Action Class** in `app/Actions/Admin/Product/CreateProductAction.php`:
```php
<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Data\Admin\Product\ProductCreateData;
use App\Models\Product;

final readonly class CreateProductAction
{
    public function handle(ProductCreateData $data): Product
    {
        return Product::create([
            'name' => $data->name,
            'price' => $data->price,
            'vendor_id' => $data->vendor_id,
            'status' => $data->status,
        ]);
    }
}
```

5. **Controller Method** in `app/Http/Controllers/Api/Admin/ProductController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Product\CreateProductAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Product\ProductCreateData;
use App\Data\Admin\Product\ProductData;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;

final class ProductController extends Controller
{
    public function store(ProductCreateData $data, CreateProductAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Product::class);
        
        $product = $action->handle($data);
        
        return response()->created(
            ProductData::from($product),
            model: Product::class
        );
    }
}
```

### **Pattern: Creating a New Customer API Endpoint**

**Example: `GET /api/v1/shop/my-orders`**

1. **Route Definition** in `routes/Api/V1/customer.php`:
```php
Route::middleware('auth:user')->name('shop.')->group(function () {
    Route::get('my-orders', [MyOrderController::class, 'index'])->name('my-orders.index');
});
```

2. **Action Class** in `app/Actions/Shop/GetMyOrdersAction.php`:
```php
<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetMyOrdersAction
{
    public function handle(int $customerId): Collection
    {
        return Order::where('customer_id', $customerId)
            ->with(['items', 'payments'])
            ->latest()
            ->get();
    }
}
```

3. **Controller** in `app/Http/Controllers/Api/Shop/MyOrderController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\GetMyOrdersAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\OrderData;
use App\Http\Controllers\Controller;

final class MyOrderController extends Controller
{
    public function index(GetMyOrdersAction $action): ApiResponseInterface
    {
        $orders = $action->handle(auth()->id());
        
        return response()->success(OrderData::collect($orders));
    }
}
```

### **Pattern: Query Builder for Complex Filtering**

**MUST** use `spatie/laravel-query-builder` for filtering, sorting, and including relationships:

```php
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

public function index(): ApiResponseInterface
{
    Gate::authorize('viewAny', Product::class);
    
    $products = QueryBuilder::for(Product::class)
        ->allowedFilters([
            'name',
            AllowedFilter::exact('status'),
            AllowedFilter::exact('vendor_id'),
            AllowedFilter::partial('description'),
        ])
        ->allowedIncludes(['vendor', 'category'])
        ->allowedSorts(['name', 'price', 'created_at'])
        ->defaultSort('-created_at')
        ->paginate(request()->integer('per_page', 15));

    return response()->success(ProductListItemData::collect($products));
}
```

### **Pattern: Data Validation Rules**

**MUST** use static `rules()` method in Data classes:

```php
public static function rules(ValidationContext $context): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required', 'email', 'max:255',
            Rule::unique('users', 'email')->ignore(
                request()->route()->parameter('user')
            ),
        ],
        'status' => ['required', 'string', Rule::enum(StatusEnum::class)],
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
    ];
}
```

### **Pattern: Response Handling**

**MUST** use appropriate response macros:

```php
// Success responses
return response()->success($data);
return response()->created($data, model: Product::class);
return response()->updated($data, model: $product);

// Error responses
return response()->validationErrors($validator->errors());
return response()->notFound(model: Product::class);
return response()->forbidden();
return response()->unauthorized();
```

### **Pattern: Permission Management System**

The system uses a centralized permission management system with auto-generated enums and database synchronization.

#### **Adding New Permissions**

1. **Edit Permission Configuration** in `config/permission-generator.php`:
```php
'resources' => [
    'blog_post' => [
        PermissionAction::VIEW_SCOPED,  // Generates: blog_post.view_any, blog_post.view
        PermissionAction::CREATE,       // Generates: blog_post.create
        PermissionAction::UPDATE,       // Generates: blog_post.update
        PermissionAction::DELETE,       // Generates: blog_post.delete
        'publish',                      // Custom: blog_post.publish
        'feature',                      // Custom: blog_post.feature
    ],
],
```

2. **Generate the PermissionEnum**:
```bash
php artisan permission:generate
```

3. **Sync to Database**:
```bash
php artisan permission:sync
```

#### **Using Permissions in Policies**

**MUST** use `PermissionEnum` for all permission checks:

```php
<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\BlogPost;

final class BlogPostPolicy
{
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_VIEW_ANY);
    }

    public function view(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_CREATE);
    }

    public function update(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_UPDATE);
    }

    public function delete(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_DELETE);
    }

    public function publish(Staff $user, BlogPost $blogPost): bool
    {
        return $user->can(PermissionEnum::BLOG_POST_PUBLISH);
    }
}
```

#### **Using Permissions in Controllers or Actions**

For direct permission checks (when not using policies):

```php
// Direct enum usage (preferred)
if ($user->can(PermissionEnum::BLOG_POST_PUBLISH)) {
    // Allow action
}

// String value when needed
if ($user->can(PermissionEnum::BLOG_POST_PUBLISH->value)) {
    // Allow action
}
```

#### **Permission Naming Convention**
- Resource permissions: `{RESOURCE}_{ACTION}` (e.g., `BLOG_POST_CREATE`)
- Scoped permissions: `{RESOURCE}_{ACTION}_ANY` and `{RESOURCE}_{ACTION}` (e.g., `BLOG_POST_VIEW_ANY`, `BLOG_POST_VIEW`)
- Custom permissions: `{RESOURCE}_{CUSTOM_ACTION}` (e.g., `BLOG_POST_PUBLISH`)

## 3. Directory Structure & Organization

### **Data Transfer Objects (DTOs)**
```
app/Data/
├── Admin/
│   ├── Order/
│   │   ├── OrderCreateData.php
│   │   ├── OrderData.php
│   │   └── OrderUpdateData.php
│   ├── Product/
│   └── User/
├── Shop/
│   ├── Customer/
│   └── MyCourses/
└── Transformer/
```

### **Actions**
```
app/Actions/
├── Admin/
│   ├── Order/
│   │   ├── CreateOrderAction.php
│   │   └── UpdateOrderAction.php
│   ├── Product/
│   └── User/
└── Shop/
    └── UpdateProfileAction.php
```

### **Controllers**
```
app/Http/Controllers/Api/
├── Admin/
│   ├── OrderController.php
│   ├── ProductController.php
│   └── UserController.php
└── Shop/
    ├── ProfileController.php
    └── MyCourses/
        └── EnrolmentController.php
```

### **Routes**
```
routes/Api/V1/
├── api.php          # Main API route file (includes others)
├── admin.php        # Admin endpoints with auth:staff
├── customer.php     # Protected customer endpoints with auth:user
├── shop.php         # Public shop endpoints
├── auth.php         # Authentication endpoints
└── select_option.php # Select option endpoints
```

## 4. Key Code Locations & Reference Points

### **Core Files**
- **Response Macros:** `app/Providers/ResponseMacroServiceProvider.php`
- **Authentication Config:** `config/auth.php` (guards: `user`, `staff`)
- **Main Route File:** `routes/Api/V1/api.php`
- **Permission System:** `config/permission-generator.php` (configure permissions), `app/Enums/PermissionEnum.php` (auto-generated enum)

### **Data Patterns**
- **Admin DTOs:** `app/Data/Admin/`
- **Customer DTOs:** `app/Data/Shop/`
- **Data Transformers:** `app/Data/Transformer/`
- **Data Casts:** `app/Data/Casts/`

### **Business Logic**
- **Admin Actions:** `app/Actions/Admin/`
- **Customer Actions:** `app/Actions/Shop/`
- **Utility Services:** `app/Services/`
- **Admin Policies:** `app/Policies/Admin/` (authorization logic with PermissionEnum)

### **Core Models**
- **User Model:** `app/Models/User.php` (Customer authentication)
- **Staff Model:** `app/Models/Staff.php` (Admin authentication)
- **Order Model:** `app/Models/Order.php`
- **Product Model:** `app/Models/Product.php`

## 5. Mandatory Conventions

### **File Naming**
- Data classes: `{Entity}CreateData.php`, `{Entity}Data.php`, `{Entity}UpdateData.php`
- Actions: `{Verb}{Entity}Action.php` (e.g., `CreateProductAction.php`)
- Controllers: `{Entity}Controller.php`

### **Namespace Organization**
- Admin-specific code: `App\{Type}\Admin\`
- Customer-specific code: `App\{Type}\Shop\`
- Shared utilities: `App\{Type}\`

### **Method Signatures**
- Action classes: `public function handle({Data} $data): {Model}`
- Controller methods: `public function {method}({Data} $data, {Action} $action): ApiResponseInterface`

### **Import Requirements**
- **ALWAYS** import: `use Illuminate\Support\Facades\Gate;`
- **ALWAYS** import: `use App\Contracts\ApiResponseInterface;`
- **ALWAYS** import specific Data classes and Action classes

## 6. Forbidden Patterns

### **NEVER DO**
- Put business logic in controllers
- Use different response formats than the macros
- Mix authentication guards
- Create routes without proper middleware
- Skip authorization checks in admin endpoints
- Use raw arrays instead of Data classes for API contracts
- Create database changes outside migrations

### **DEPRECATED PATTERNS**
- Direct model creation in controllers
- Manual response JSON formatting
- Inline validation rules in controllers
- Direct database queries in controllers
