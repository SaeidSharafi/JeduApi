Combines strict project architectural rules + general Laravel Boost best practices. **Project-specific rules mandatory, no exceptions.**

## 1. Guiding Principles & Development Philosophy

Guide code generation:

*   **Digests = Single Source of Truth:** Read project digest files in `docs/Digestions/` before write/modify code.
    *   `DIGEST_DATA_MODELS.md`: Explain DB models + relationships.
    *   `DIGEST_CORE_LOGIC.md`: Detail Actions/Services architecture + core business logic.
    *   `DIGEST_API_INTERFACES.md`: Document API endpoints, routes, DTO contracts.
    *   `DIGEST_SCHEMA.md`: Complete DB schema.
        Files are application blueprint. Trust them, don't infer architecture. Save time, prevent incorrect assumptions.
*   **Prioritize Minimal Impact:** Understand context (routes, controllers, actions, DTOs) before modify. Aim for smallest change fulfilling requirement. Preserve API contracts. Avoid unnecessary refactor.
*   **Targeted Implementation:** Modify only essential code. Preserve unrelated code for API stability.
*   **Clarify Ambiguity:** Ask clarification if scope (API behavior, response structure, validation rules) unclear. No assumptions.
*   **Adhere to Code Quality Standards:**
    *   **Consistency:** Strict follow existing project patterns, RESTful principles, DTO/Action architecture.
    *   **Robust Error Handling:** Use project custom response macros for success/error responses.
    *   **Security:** Implement security via established patterns: `spatie/laravel-data` for validation, Gates/Policies for authorization, built-in authentication.

## 2. Project Glossary: Core Concepts & Terminology

Defines essential entities in Jedu E-Commerce platform. Understand concepts to interpret tasks + modify codebase.

### The Product Catalog: From Concept to SKU

Catalog uses three-layer model separating academic content from commercial packaging.

-   **Productable (Course, Seminar, DigitalAsset):** **Academic blueprint** / intellectual property. Abstract concept representing raw content. **Not directly sellable**.
    -   **Course:** Blueprint for multi-session educational program. Defines curriculum, difficulty, learning outcomes.
    -   **Seminar:** Blueprint for one-off event, workshop, webinar.
    -   **DigitalAsset:** Blueprint for standalone content (PDF, video, software tool).
-   **Product:** **Commercial shell**. Makes `Productable` sellable wrapping business context: **who** sells (`Vendor`), **when** offered (`Term`). Single `Course` blueprint sellable as multiple `Products`.
-   **ProductDeliveryOption:** **Concrete SKU** added to cart. Defines specific price, format, purchase terms for `Product`. Single `Product` has many delivery options. Holds price.
-   **Vendor:** Internal department/external entity selling products.
-   **Term:** Academic period/semester (e.g., "Fall 2025"). Time-based context for `Products`.
-   **Category:** Hierarchical tag organizing/classifying `Productables`.
-   **Teacher:** Instructor profile with biography, qualifications, metadata. Linkable to `ProductDeliveryOption`.

### The Sales & Fulfillment Engine

Manage transaction lifecycle, purchase to access.

-   **Order:** Master record for customer transaction. Primary receipt summarizing items, discounts, totals.
-   **OrderItem:** Single line item in `Order` corresponding to purchased `ProductDeliveryOption`. Contains immutable JSON snapshot of product data at purchase time.
-   **Payment:** Financial transaction record (wallet, bank transfer) applied to `Order`.
-   **Enrollment:** Link between purchase + content access. Created automatically after successful `OrderItem` payment. Grants `User` access to purchased `ProductDeliveryOption`. Definitive "proof of access".

### The Commercial & Engagement Engine

Drive marketing, promotions, customer value.

-   **DiscountPromotion:** Discount engine core. Defines rules (`DiscountPromotionRule`) determining eligibility (Conditions) + outcomes (Actions) for promotions.
-   **Wallet:** User personal credit balance system. Immutable ledger recording credits/debits as `WalletTransaction` entries.
-   **WalletTransaction:** Immutable record changing `Wallet` balance (deposit, withdrawal, gift). Perfect audit trail.
-   **WalletCampaign:** Admin tool for marketing campaigns granting wallet credits bulk, triggered by system events.

### Content & Administration

Power CMS, blog, administrative backbone.

-   **Staff:** Admin user account with permissions managed by role-based access control.
-   **AdminActionLog:** Comprehensive immutable audit trail recording every action by `Staff`. Used for security, compliance.
-   **Review:** Customer-submitted rating/comment for `Productable`. Follows approval workflow.
-   **BlogPost:** Article in CMS. `BlogPosts` linkable to `Productables` for marketing/supplementary content.
-   **Setting:** Key-value record in DB powering dynamic storefront content. Managed via `SettingsService`.
-   **HomePageBlock, Slider, Partner, StudentStory:** Dynamic admin-managed models building homepage/marketing site sections.

## 3. Core Mandates & Unbreakable Rules

Required architecture for JeduShop API.

*   **Use `sail`:** Run `artisan` commands via Sail (e.g., `sail artisan make:model`, `sail artisan permission:generate`).
*   **API Contract (Data Transfer Objects):**
    *   **MUST** use `spatie/laravel-data` for **ALL** API requests/responses. **DO NOT USE** Laravel `Form Requests` or `API Resources`.
    *   Every controller method **MUST** accept Data class for request, return Data class for response.
    *   Place all Data classes in `app/Data/` with proper namespace.
    *   Request Data classes **MUST** implement `rules()` (validation) + `bodyParameters()` (Scribe API documentation).
    *   Add `@codeCoverageIgnore` to `bodyParameters()` excluding test coverage.
    *   **Controller DocBlocks for Scribe Documentation:**
        *   Docblocks for high-level organization/description. Scribe reads request body details from type-hinted Data class.
        *   **Class DocBlock:** API controller **MUST** have docblock with `@group` organizing generated docs.
        *   Add `@authenticated` to class docblock if authentication required.
        *   **Method DocBlock:** Method **MUST** have concise action description. Follow response file rules.
        *   **`@responseFile` Convention:** Mandatory path structure: `resources/responses/<scope>/<resource>/<action>.json`.
            *   **`<scope>`:** `admin` or `shop`.
            *   **`<resource>`:** Lowercase folder matching resource (e.g., `slider`, `product`). Don't place directly in `<scope>`.
            *   **`<action>.json`:**
                *   `index.json` for lists/collections (e.g., `index()`).
                *   `show.json` for single resource (e.g., `store()`, `show()`, `update()`).
        *   **Delete methods** (returning 204 No Content) **MUST NOT** have `@responseFile`.
        *   **Single Source of Truth Principle:** Request validation rules + `bodyParameters()` in Data class = single source of truth. **MUST NOT** add `@bodyParam` in controller docblock if using Data class.
        *   Don't include auth details in docblocks. Handled globally.
        *   Don't include route binded parameters in docblocks. Scribe auto-documents.
        *   **CORRECT (Example for `store` or `update` method):**
            ```php
            <?php

            namespace App\Http\Controllers\Api\V1\Admin\Slider;

            // ...

            /**
             * @group Slider Management
             * @subgroup Sliders
             */
            final class SliderController
            {
                /**
                 * Create a new Slider.
                 *
                 * This endpoint allows an authorized admin to create a new slider.
                 *
                 * @responseFile resources/responses/admin/slider/show.json
                 */
                public function store(SliderCreateData $data, CreateSliderAction $action): JsonResponse
                {
                    // ...
                }
            }
            ```
    *   **Scribe Documentation for Arrays of Objects:**
        *   Scribe cannot infer examples for sub-fields of array of objects (e.g., `main_links.*.title`). **MUST** explicitly define description/example for **EACH** sub-field using `.*.` syntax in `bodyParameters()`.
        *   **INCORRECT (Scribe will fail to generate examples for `title` and `link`):**
            ```php
            'main_links' =>[
                'description' => 'An array of main links to display in the footer.',
                'example' => [['title' => 'Home', 'link' => '/home'],
                    ['title' => 'About Us', 'link' => '/about'],
                ],
            ],
            ```
        *   **CORRECT (Explicitly defines each sub-field for Scribe):**
            ```php
            'main_links'            =>[
                'description' => 'An array of main links to display in the footer.',
                'example'     => [['title' => 'Home', 'link' => '/home'],
                    ['title' => 'About Us', 'link' => '/about'],
                ],
            ],
            'main_links.*.title'    =>[
                'description' => 'The display text for the link.',
                'example'     => 'Home',
            ],
            'main_links.*.link'     =>[
                'description' => 'The URL for the link.',
                'example'     => '/home',
            ],
            ```
*   **Business Logic Separation:**
    *   Place all business logic in Action classes in `app/Actions/`.
    *   Controllers **MUST** be thin, delegate to Action class. **NO** business logic in controllers.
    *   Service classes (`app/Services/`) **ONLY** for generic reusable utilities (e.g., payment gateway interface).
*   **Routing & Authentication:**
    *   Auth guards applied automatically. **DO NOT** add `middleware('auth:...')` to routes.
    *   Route file strict assignments:
        *   **Admin endpoints:** `routes/Api/V1/admin/admin.php` (`auth:staff`)
        *   **Customer endpoints:** `routes/Api/V1/customer.php` (`auth:user`)
        *   **Public endpoints:** `routes/Api/V1/shop/shop.php` (no guard)
    *   **NEVER** mix route types in wrong files.
*   **API Responses:**
    *   Controller methods **MUST** return responses using custom macros from `app/Providers/ResponseMacroServiceProvider.php` (e.g., `response()->success()`, `response()->notFound()`).
*   **Authorization:**
    *   Admin controller method **MUST** include `Gate::authorize()` using appropriate policy method.
    *   Policies in `app/Policies/Admin/` **MUST** use `PermissionEnum` for permission checks.
    *   Register policies in `app/Providers/AuthServiceProvider.php`.
*   **Permission System:**
    *   **MUST** use `PermissionEnum` from `app/Enums/PermissionEnum.php` for permission checks.
    *   Add permissions: Edit `config/permission-generator.php`, run `sail artisan permission:generate` + `sail artisan permission:sync`.
    *   **DO NOT** manually edit `PermissionEnum.php` or use hardcoded strings.

## 4. Testing with PEST and AuthTestTrait

*   **Framework:** **MUST** use PEST for feature tests.
*   **Authentication:** **MUST** use custom `AuthTestTrait` for user auth in tests. **DO NOT** use standard Laravel `actingAs()`.
*   **Available `AuthTestTrait` Methods:**
    *   `$this->authorized_user(array $permissions, 'staff'|'user')`
    *   `$this->unauthorized_user('staff'|'user')`
    *   `$this->customer(?User $user = null)`
    *   `$this->admin_user()`
*   **Example Test:**
    ```php
    use App\Enums\PermissionEnum;
    use function Pest\Laravel\postJson;

    it('can create a product with the correct permissions', function () {
        // Arrange
        $this->authorized_user([PermissionEnum::PRODUCT_CREATE]); // Authenticates a staff user with permission
        $productData =['name' => 'New Awesome Product', /* ... */];

        // Act
        $response = postJson(route('admin.product.store'), $productData);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name' => 'New Awesome Product']);
    });
    ```
*[General testing best practices are now in the laravel-boost-guidelines section]*

## 5. Commit Message Convention

*   **Conventional Commits:** **MUST** follow Conventional Commits specification.
*   **Format:** `type(scope): imperative description`.
*   **Example:** `feat(api-product): add endpoint for creating new products`

---
## 6. Architectural Patterns, Directory Structure & Forbidden Patterns

*(Unchanged section summary)*
*   **Pattern: Creating a New Admin API Endpoint**
*   **Directory Structure & Organization** (DTOs, Actions, Controllers, Routes)
*   **Key Code Locations & Reference Points**
*   **Mandatory Conventions** (File Naming, Namespaces, etc.)
*   **Forbidden Patterns** (Important rules summary)
    *   **DO NOT** put business logic in controllers.
    *   **DO NOT** use Laravel `Form Requests` or `API Resources`. Use `spatie/laravel-data` DTOs.
    *   **DO NOT** manually add `auth` middleware to routes.
    *   **DO NOT** skip authorization checks (`Gate::authorize()`) in admin endpoints.
    *   **DO NOT** use `actingAs()` in tests. Use `AuthTestTrait` helpers.
    *   **DO NOT** use `RefreshDatabase` trait in tests.
    *   **DO NOT** run `php artisan` directly. **ALWAYS** use `sail artisan`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/nightwatch (NIGHTWATCH) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v10
- laravel/telescope (TELESCOPE) - v5
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `configuring-horizon` — Use this skill whenever the user mentions Horizon by name in a Laravel context. Covers the full Horizon lifecycle: installing Horizon (horizon:install, Sail setup), configuring config/horizon.php (supervisor blocks, queue assignments, balancing strategies, minProcesses/maxProcesses), fixing the dashboard (authorization via Gate::define viewHorizon, blank metrics, horizon:snapshot scheduling), and troubleshooting production issues (worker crashes, timeout chain ordering, LongWaitDetected notifications, waits config). Also covers job tagging and silencing. Do not use for generic Laravel queues without Horizon, SQS or database drivers, standalone Redis setup, Linux supervisord, Telescope, or job batching.
- `scout-development` — Develops full-text search with Laravel Scout. Activates when installing or configuring Scout; choosing a search engine (Algolia, Meilisearch, Typesense, Database, Collection); adding the Searchable trait to models; customizing toSearchableArray or searchableAs; importing or flushing search indexes; writing search queries with where clauses, pagination, or soft deletes; configuring index settings; troubleshooting search results; or when the user mentions Scout, full-text search, search indexing, or search engines in a Laravel project. Make sure to use this skill whenever the user works with search functionality in Laravel, even if they don&#039;t explicitly mention Scout.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use strict typing at the head of a `.php` file: `declare(strict_types=1);`.
- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `vendor/bin/sail artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `vendor/bin/sail artisan make:test --pest {name}`.
- Run tests: `vendor/bin/sail artisan test --compact --parallel` or filter: `vendor/bin/sail artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.
</laravel-boost-guidelines>
