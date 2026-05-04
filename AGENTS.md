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
        *   **`@responseFile` Convention:** Mandatory path structure: `storage/responses/<scope>/<resource>/<action>.json`.
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
                 * @responseFile storage/responses/admin/slider/show.json
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

Curated by Laravel maintainers. Follow closely.

## Foundational Context
Expert with specific packages & versions:
- php - 8.4.18
- laravel/framework (LARAVEL) - v12
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

## Conventions
- Follow existing code conventions. Check sibling files for structure, approach, naming.
- Use descriptive names. Example: `isRegisteredForDiscounts`, not `discount()`.
- Reuse existing components before writing new.

## Verification Scripts
- Don't create verification scripts/tinker if tests cover functionality. Unit/feature tests > scripts.

## Application Structure & Architecture
- Stick to existing directory structure. No new base folders without approval.
- Don't change dependencies without approval.

## Frontend Bundling
- If UI missing changes, run `npm run build`, `npm run dev`, or `composer run dev`. Ask user.

## Replies
- Concise explanations. Focus on importance, skip obvious details.

## Documentation Files
- Create documentation only if explicitly requested.

=== boost rules ===

## Laravel Boost
- MCP server with powerful tools. Use them.

## Artisan
- Use `list-artisan-commands` to verify parameters.

## URLs
- Share URLs using `get-absolute-url` tool ensuring correct scheme, domain/IP, port.

## Tinker / Debugging
- Use `tinker` tool to execute PHP/debug/query Eloquent models.
- Use `database-query` tool to read from DB.

## Reading Browser Logs With the `browser-logs` Tool
- Read logs/errors/exceptions using `browser-logs` tool.
- Use recent logs only. Ignore old logs.

## Searching Documentation (Critically Important)
- Use `search-docs` tool before other approaches. Returns version-specific docs automatically passing installed packages. Pass package array to filter.
- Perfect for Laravel ecosystem (Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.).
- Search docs before code changes ensuring correct approach.
- Use multiple, broad, simple, topic-based queries. Example: `['rate limiting', 'routing rate limiting', 'routing']`.
- No package names in queries. Example: `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- Pass multiple queries at once.
1. Simple Word Searches (auto-stemming): query=authentication -> finds 'authenticate', 'auth'
2. Multiple Words (AND Logic): query=rate limit -> finds "rate" AND "limit"
3. Quoted Phrases (Exact Position): query="infinite scroll" -> exact adjacent match
4. Mixed Queries: query=middleware "rate limit"
5. Multiple Queries: queries=["authentication", "middleware"] -> ANY term

=== php rules ===

## PHP

- Strict typing head of `.php`: `declare(strict_types=1);`.
- Always use curly braces for control structures.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- No empty `__construct()` with zero parameters.

### Type Declarations
- Explicit return type declarations for methods/functions.
- Appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. No inline code comments unless very complex.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays.

## Enums
- Keys TitleCase. Example: `FavoritePerson`, `BestLake`, `Monthly`.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `sail artisan make:` to create files. List available via `list-artisan-commands`.
- Generic PHP class: `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands. Pass correct `--options`.

### Database
- Use Eloquent relationship methods with return type hints. Prefer over raw queries/manual joins.
- Use Eloquent models/relationships before raw DB queries.
- Avoid `DB::`; prefer `Model::query()`. Leverage ORM.
- Prevent N+1 query problems via eager loading.
- Use query builder for very complex DB operations.

### Model Creation
- Create factories + seeders for new models. Ask user if needed using `list-artisan-commands` options.

### APIs & Eloquent Resources
- Default to Eloquent API Resources + API versioning unless existing routes differ. Follow existing conventions.

### Controllers & Validation
- Create Form Request classes for validation. No inline validation. Include validation rules + custom error messages.
- Check sibling Form Requests for array vs string validation rules.

### Queues
- Use queued jobs for time-consuming operations with `ShouldQueue` interface.

### Authentication & Authorization
- Use built-in auth/authorization (gates, policies, Sanctum).

### URL Generation
- Prefer named routes + `route()` function.

### Configuration
- Use env vars only in config files. Never `env()` outside config. Use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- Use model factories for tests. Check factory custom states before manual setup.
- Faker: Use `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions.
- Create tests: `sail artisan make:test [options] <name>` (feature test) or `--unit` (unit test). Most tests should be feature tests.

=== laravel/v12 rules ===

## Laravel 12

- Use `search-docs` tool for version-specific docs.
- Uses streamlined file structure from Laravel 11+.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- Register middleware, exceptions, routing in `bootstrap/app.php`.
- `bootstrap/providers.php` contains app-specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php`.
- **Commands auto-register** - `app/Console/Commands/` automatically available.

### Database
- Modifying column: migration must include all previous column attributes. Otherwise dropped.
- Laravel 11+ limits eagerly loaded records natively: `$query->latest()->limit(10);`.

### Models
- Set casts in `casts()` method on model, not `$casts` property. Follow existing conventions.

=== pint/core rules ===

## Laravel Pint Code Formatter

- Run `vendor/bin/pint --dirty` before finalizing changes.
- Don't run `vendor/bin/pint --test`, run `vendor/bin/pint` to fix issues.

=== pest/core rules ===

## Pest

### Testing
- Verify feature works via Unit/Feature test.

### Pest Tests
- Write using Pest: `sail artisan make:test --pest <name>`.
- Never remove tests/test files without approval. Core to app.
- Test happy paths, failure paths, weird paths.
- Lives in `tests/Feature` and `tests/Unit`.
- Format:
  <code-snippet name="Basic Pest Test Example" lang="php">
  it('is true', function () {
  expect(true)->toBeTrue();
  });
  </code-snippet>

### Running Tests
- Run minimal tests via filter before finalizing.
- All tests: `sail artisan test`.
- File tests: `sail artisan test tests/Feature/ExampleTest.php`.
- Filter: `sail artisan test --filter=testName`.
- Ask to run entire suite after related tests pass.

### Pest Assertions
- Use specific assert methods (`assertForbidden`, `assertNotFound`) instead of `assertStatus()`.
  <code-snippet name="Pest Example Asserting postJson Response" lang="php">
  it('returns all', function () {
  $response = $this->postJson('/api/docs',[]);

  $response->assertSuccessful();
  });
  </code-snippet>

### Mocking
- Use `Pest\Laravel\mock` function imported via `use function Pest\Laravel\mock;`. Or `$this->mock()` if existing tests do.
- Create partial mocks same way.

### Datasets
- Use Pest datasets to simplify tests with duplicated data (e.g., validation rules).
  <code-snippet name="Pest Dataset Example" lang="php">
  it('has emails', function (string $email) {
  expect($email)->not->toBeEmpty();
  })->with([
  'james' => 'james@laravel.com',
  'taylor' => 'taylor@laravel.com',
  ]);
  </code-snippet>

=== pest/v4 rules ===

## Pest 4

- Offers browser testing, smoke testing, visual regression, test sharding, fast type coverage.
- Browser tests live in `tests/Browser/`.
- Use `search-docs` tool for guidance.

### Browser Testing
- Use Laravel features (`Event::fake()`, `assertAuthenticated()`, model factories, `RefreshDatabase`) in Pest v4 browser tests.
- Interact with page (click, type, scroll, drag-and-drop, etc.).
- Test multiple browsers/devices/viewports/color schemes if requested.
- Take screenshots/pause for debugging.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>

=== tests rules ===

## Test Enforcement

- Programmatically test every change. Write/update test, run affected tests.
- Run minimum tests needed. Use `sail artisan test` with filter.
  </laravel-boost-guidelines>
