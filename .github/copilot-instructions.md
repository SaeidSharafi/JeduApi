This document provides a comprehensive set of instructions for GitHub Copilot. It combines general best practices with the strict architectural rules specific to this project. **These rules are mandatory and there are no exceptions.**

## 1. Guiding Principles & Development Philosophy

These principles should guide all code generation:

*   **Prioritize Minimal Impact:** Before modification, understand the context (routes, controllers, actions, DTOs). Aim for the smallest possible change that fulfills the requirement while preserving existing API contracts. Avoid unnecessary refactoring.
*   **Targeted Implementation:** Identify and modify only the essential code sections. Preserve unrelated code to maintain API stability.
*   **Clarify Ambiguity:** If the required scope (API behavior, response structure, validation rules) is unclear, ask for clarification before proceeding. Do not make assumptions.
*   **Adhere to Code Quality Standards:**
    *   **Clarity & Readability:** Use descriptive names and follow PSR-12 conventions.
    *   **Consistency:** Strictly follow the existing project patterns, RESTful principles, and the established DTO/Action architecture.
    *   **Robust Error Handling:** Use the project's custom response macros for all success and error responses.
    *   **Security:** Implement security through the established patterns: `spatie/laravel-data` for validation, Gates and Policies for authorization, and the project's built-in authentication.

## 2. Core Mandates & Unbreakable Rules

This is the required architecture for the JeduShop API.

*   **Use `sail`:** All `artisan` commands **MUST** be run through Sail. For example, use `sail artisan make:model` or `sail artisan permission:generate`.

*   **API Contract (Data Transfer Objects):**
    *   **MUST** use `spatie/laravel-data` for **ALL** API requests and responses. **DO NOT USE** Laravel's `Form Requests` or `API Resources`.
    *   Every controller method **MUST** accept a Data class for requests and return a Data class in responses.
    *   in controllers add the docblock for scribe API documentation before the class definition:
        ```php
        /**
         * @group Admin - Products
         * 
         * Manage products in the system.
         */
        ```
    * for each controller method, add a docblock describing the parameters (only query parameters), and responses for Scribe documentation:
        ```php
        /**
         * Display a listing of the products.
         *
         * @queryParam filter[name] string Filter by product name. Example: Laptop
         * @queryParam sort string Sort by field. Prefix with '-' for descending. Example: -created_at
         * @queryParam page[number] integer Page number for pagination. Example: 1
         * @queryParam page[size] integer Number of items per page. Example: 15
         *
         * @response 200 {
         *   "data": [
         *     {
         *       "id": 1,
         *       "name": "Laptop",
         *       "price": 999.99,
         *       // ...
         *     }
         *   ],
         *   "meta": {
         *     "current_page": 1,
         *     "last_page": 10,
         *     // ...
         *   }
         * }
         */
        ```
        if there is nor QUery parameters, you can skip the `@queryParam` section.
    *   you can create json files in storage/responses for complex response examples and reference them in the docblock:
        ```php
        /**
         * @responseFile 200 storage/responses/shop/products/index.json
         */
        ```
        for most of the controllers we use index and show response examples.
        for general responses like 403, 404 and 422 we have already exisit file ins storage/responses.
    *   All Data classes **MUST** be placed in `app/Data/` with proper namespace organization.
    *   Request Data classes **MUST** implement both `rules()` for validation and `bodyParameters()` for Scribe API documentation.
    * 

*   **Business Logic Separation:**
    *   All business logic **MUST** be placed in Action classes within `app/Actions/`.
    *   Controllers **MUST** be thin and delegate immediately to an Action class. **NO** business logic in controllers.
    *   Service classes (`app/Services/`) are **ONLY** for generic, reusable utilities (e.g., interfacing with a payment gateway).

*   **Routing & Authentication:**
    *   Authentication guards are applied automatically in the application bootstrap based on the route file. **DO NOT** add `middleware('auth:...')` to route definitions.
    *   **Admin endpoints** (`/api/v1/admin/*`) **MUST** be placed in `routes/Api/V1/admin/admin.php` or a file included within it. These routes are automatically protected by the `auth:staff` guard.
    *   **Customer endpoints** (`/api/v1/customer/*`) **MUST** be placed in `routes/Api/V1/customer.php`. These routes are automatically protected by the `auth:user` guard.
    *   **Public endpoints** (`/api/v1/shop/*`) **MUST** be placed in `routes/Api/V1/shop/shop.php`. These routes have no authentication guard.
    *   **NEVER** mix route types in the wrong files.

*   **Database Schema:**
    *   All database changes **MUST** be done through migration files. Direct database alteration is **FORBIDDEN**.

*   **API Responses:**
    *   All controller methods **MUST** return responses using the custom response macros from `app/Providers/ResponseMacroServiceProvider.php`.
    *   Available macros: `response()->success()`, `response()->created()`, `response()->updated()`, `response()->validationErrors()`, `response()->notFound()`, `response()->forbidden()`, `response()->error()`, `response()->unauthorized()`.

*   **Authorization:**
    *   Every admin controller method **MUST** include a `Gate::authorize()` call using the appropriate policy method (`viewAny`, `view`, `create`, `update`, `delete`).
    *   Policies are located in `app/Policies/Admin/` and **MUST** use `PermissionEnum` for permission checks.
    *   register policies in `app/Providers/AuthServiceProvider.php`.

*   **Permission System:**
    *   **MUST** use `PermissionEnum` from `app/Enums/PermissionEnum.php` for all permission checks.
    *   To add permissions:
        1.  Edit `config/permission-generator.php`.
        2.  Run `sail artisan permission:generate`.
        3.  Run `sail artisan permission:sync`.
    *  **DO NOT** manually edit `PermissionEnum.php`.
    *  **DO NOT** use hardcoded permission strings.
    *  **DO NOT** skip permission checks in admin endpoints.


## 3. Testing with PEST and AuthTestTrait

*   **Framework:** **MUST** use PEST for all feature tests.
*   **Authentication:** **MUST** use the custom `AuthTestTrait` for authenticating users in tests. **DO NOT** use Laravel's standard `actingAs()`.

*   **Available `AuthTestTrait` Methods:**
    *   `$this->authorized_user(array $permissions, 'staff'|'user')`: Creates and authenticates a user/staff with a temporary role that has the specified permissions. Use this for testing authorization rules.
    *   `$this->unauthorized_user('staff'|'user')`: Creates and authenticates a user/staff with no permissions.
    *   `$this->customer(?User $user = null)`: Creates and authenticates a specific or new customer with the `user` guard.
    *   `$this->admin_user()`: Creates and authenticates a staff member with `is_admin = true`.

*   **Example Test:**
    ```php
    use App\Enums\PermissionEnum;
    use function Pest\Laravel\postJson;

    it('can create a product with the correct permissions', function () {
        // Arrange
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]); // Authenticates a staff user with permission
        $productData = [
            'name' => 'New Awesome Product',
            'price' => 99.99,
            // ... other data
        ];

        // Act
        $response = postJson(route('admin.product.store'), $productData);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name' => 'New Awesome Product']);
    });

    it('cannot create a product without the correct permissions', function () {
        // Arrange
        $this->unauthorized_user(); // Authenticates a staff user with NO permissions
        $productData = ['name' => 'New Awesome Product'];

        // Act
        $response = postJson(route('admin.product.store'), $productData);

        // Assert
        $response->assertForbidden();
    });
    ```

*   **General Test Practices:**
    *   Cover success cases (2xx), client errors (4xx - validation, auth), and server errors (5xx).
    *   Use descriptive test names (`it('verb noun')`).
    *   Use the Arrange-Act-Assert pattern.
    *   Use model factories for test data setup.
    *   Mock external dependencies and services.
    *   pest functions (like `postJson()`) can also be called like `$this->postJson()` within test closures.

## 4. Commit Message Convention

*   **Conventional Commits:** All commit messages **MUST** follow the Conventional Commits specification.
*   **Format:** `type(scope): imperative description`.
    *   **Type:** `feat`, `fix`, `chore`, `refactor`, `test`, `docs`, etc.
    *   **Scope:** A noun describing the affected part of the codebase (e.g., `api-controller`, `product-action`, `validation`, `auth`, `routes`).
    *   **Example:** `feat(api-product): add endpoint for creating new products`
    *   **Example:** `fix(validation): correct price validation rule in ProductCreateData`

---

## 5. Architectural Patterns & "How-To" Guides

### **Pattern: Creating a New Admin API Endpoint**

**Example: `POST /api/v1/admin/products`**

1.  **Route Definition** in `routes/Api/V1/admin/admin.php`:
    *(Note: `auth:staff` guard is applied automatically to this file)*
    ```php
    Route::apiResource('product', ProductController::class);
    ```

2.  **Request DTO with Scribe Docs** in `app/Data/Admin/Product/ProductCreateData.php`:
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

        /**
         * @codeCoverageIgnore
         * @return array<string, array<string, mixed>>
         */
        public function bodyParameters(): array
        {
            return [
                'name' => [
                    'description' => 'The name of the product.',
                    'example' => 'Wireless Mouse',
                ],
                'price' => [
                    'description' => 'The price of the product.',
                    'example' => 125.50,
                ],
                'vendor_id' => [
                    'description' => 'The ID of the associated vendor.',
                    'example' => 1,
                ],
                'status' => [
                    'description' => 'The publication status.',
                    'example' => 'published',
                ],
            ];
        }
    }
    ```
    *(The `Action` and `Controller` examples remain the same as they were already correct)*

### **Pattern: Creating a New Customer API Endpoint**

**Example: `GET /api/v1/customer/my-orders`**

1.  **Route Definition** in `routes/Api/V1/customer.php`:
    *(Note: `auth:user` guard is applied automatically to this file)*
    ```php
    Route::get('my-orders', [MyOrderController::class, 'index'])->name('my-orders.index');
    ```
    *(The `Action` and `Controller` examples remain the same)*

---

## 6. Directory Structure & Organization

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
├── api.php               # Main API route file (includes others)
├── admin/                # Admin routes group with auth:staff and admin.audit middleware (setup in bootstrap/app.php)
    ├── admin.php         # general Admin endpoints
    ├── catalog.php       # Catalog management endpoints
    ├── sale.php          # Order and Discount management endpoints
    ├── select_option.php # Select option endpoints
    ├── setting.php       # Settings management endpoints
    └── wallet.php        # Wallet management endpoints
├── shop/                 # Public shop routes group
    └── shop.php          # general Shop endpoints
├── customer.php          # Protected customer endpoints with auth:user
└── auth.php              # Authentication endpoints
```

## 7. Key Code Locations & Reference Points

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

## 8. Mandatory Conventions
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

## 9. Forbidden Patterns

*   **DO NOT** put business logic in controllers.
*   **DO NOT** use different response formats than the provided macros.
*   **DO NOT** use Laravel `Form Requests` or `API Resources`. Use `spatie/laravel-data` DTOs instead.
*   **DO NOT** manually add `auth` middleware to route files.
*   **DO NOT** mix route types (admin, customer, public) in the wrong files.
*   **DO NOT** skip authorization checks (`Gate::authorize()`) in admin endpoints.
*   **DO NOT** use raw arrays instead of Data classes for any API contract.
*   **DO NOT** create database changes outside of migration files.
*   **DO NOT** use `actingAs()` in tests. Use the `AuthTestTrait` helpers.
*   **DO NOT** use `RefreshDatabase` trait in tests. it's handled by the test suite setup.
*   **DO NOT** run `php artisan` directly. **ALWAYS** use `sail artisan`.
