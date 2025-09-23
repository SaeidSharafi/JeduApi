This document provides a comprehensive set of instructions for GitHub Copilot. It combines the strict architectural rules specific to this project with general best practices from the Laravel Boost guidelines. **The project-specific rules are mandatory and there are no exceptions.**

## 1. Guiding Principles & Development Philosophy

These principles should guide all code generation:

*   **Prioritize Minimal Impact:** Before modification, understand the context (routes, controllers, actions, DTOs). Aim for the smallest possible change that fulfills the requirement while preserving existing API contracts. Avoid unnecessary refactoring.
*   **Targeted Implementation:** Identify and modify only the essential code sections. Preserve unrelated code to maintain API stability.
*   **Clarify Ambiguity:** If the required scope (API behavior, response structure, validation rules) is unclear, ask for clarification before proceeding. Do not make assumptions.
*   **Adhere to Code Quality Standards:**
    *   **Consistency:** Strictly follow the existing project patterns, RESTful principles, and the established DTO/Action architecture.
    *   **Robust Error Handling:** Use the project's custom response macros for all success and error responses.
    *   **Security:** Implement security through the established patterns: `spatie/laravel-data` for validation, Gates and Policies for authorization, and the project's built-in authentication.

## 2. Core Mandates & Unbreakable Rules

This is the required architecture for the JeduShop API.

*   **Use `sail`:** All `artisan` commands **MUST** be run through Sail. For example, use `sail artisan make:model` or `sail artisan permission:generate`.

*   **API Contract (Data Transfer Objects):**
    *   **MUST** use `spatie/laravel-data` for **ALL** API requests and responses. **DO NOT USE** Laravel's `Form Requests` or `API Resources`.
    *   Every controller method **MUST** accept a Data class for requests and return a Data class in responses.
    *   In controllers add the docblock for Scribe API documentation before the class definition.
    *   For each controller method, add a docblock describing the parameters and responses for Scribe documentation, including `@responseFile` for complex examples.
    *   All Data classes **MUST** be placed in `app/Data/` with proper namespace organization.
    *   Request Data classes **MUST** implement both `rules()` for validation and `bodyParameters()` for Scribe API documentation.

*   **Business Logic Separation:**
    *   All business logic **MUST** be placed in Action classes within `app/Actions/`.
    *   Controllers **MUST** be thin and delegate immediately to an Action class. **NO** business logic in controllers.
    *   Service classes (`app/Services/`) are **ONLY** for generic, reusable utilities (e.g., interfacing with a payment gateway).

*   **Routing & Authentication:**
    *   Authentication guards are applied automatically. **DO NOT** add `middleware('auth:...')` to route definitions.
    *   Route files have strict assignments:
        *   **Admin endpoints:** `routes/Api/V1/admin/admin.php` (`auth:staff`)
        *   **Customer endpoints:** `routes/Api/V1/customer.php` (`auth:user`)
        *   **Public endpoints:** `routes/Api/V1/shop/shop.php` (no guard)
    *   **NEVER** mix route types in the wrong files.

*   **API Responses:**
    *   All controller methods **MUST** return responses using the custom response macros from `app/Providers/ResponseMacroServiceProvider.php` (e.g., `response()->success()`, `response()->notFound()`).

*   **Authorization:**
    *   Every admin controller method **MUST** include a `Gate::authorize()` call using the appropriate policy method.
    *   Policies are located in `app/Policies/Admin/` and **MUST** use `PermissionEnum` for permission checks.
    *   Register policies in `app/Providers/AuthServiceProvider.php`.

*   **Permission System:**
    *   **MUST** use `PermissionEnum` from `app/Enums/PermissionEnum.php` for all permission checks.
    *   To add permissions: Edit `config/permission-generator.php`, then run `sail artisan permission:generate` and `sail artisan permission:sync`.
    *   **DO NOT** manually edit `PermissionEnum.php` or use hardcoded permission strings.

## 3. Testing with PEST and AuthTestTrait

*   **Framework:** **MUST** use PEST for all feature tests.
*   **Authentication:** **MUST** use the custom `AuthTestTrait` for authenticating users in tests. **DO NOT** use Laravel's standard `actingAs()`.

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
        $productData = ['name' => 'New Awesome Product', /* ... */];

        // Act
        $response = postJson(route('admin.product.store'), $productData);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name' => 'New Awesome Product']);
    });
    ```
*[General testing best practices are now in the laravel-boost-guidelines section]*

## 4. Commit Message Convention

*   **Conventional Commits:** All commit messages **MUST** follow the Conventional Commits specification.
*   **Format:** `type(scope): imperative description`.
*   **Example:** `feat(api-product): add endpoint for creating new products`

---
## 5. Architectural Patterns, Directory Structure & Forbidden Patterns

*(This section remains unchanged as it contains highly project-specific examples, directory structures, naming conventions, and a summary of the most critical "DO NOT" rules.)*

*   **Pattern: Creating a New Admin API Endpoint**
*   **Directory Structure & Organization** (DTOs, Actions, Controllers, Routes)
*   **Key Code Locations & Reference Points**
*   **Mandatory Conventions** (File Naming, Namespaces, etc.)
*   **Forbidden Patterns** (A summary of the most important rules from above)
    *   **DO NOT** put business logic in controllers.
    *   **DO NOT** use Laravel `Form Requests` or `API Resources`. Use `spatie/laravel-data` DTOs.
    *   **DO NOT** manually add `auth` middleware to route files.
    *   **DO NOT** skip authorization checks (`Gate::authorize()`) in admin endpoints.
    *   **DO NOT** use `actingAs()` in tests. Use the `AuthTestTrait` helpers.
    *   **DO NOT** use `RefreshDatabase` trait in tests.
    *   **DO NOT** run `php artisan` directly. **ALWAYS** use `sail artisan`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.7
- laravel/framework (LARAVEL) - v12
- ... (rest of packages) ...

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.
- **Note:** This project requires using `sail` for all commands (e.g., `sail artisan make:class`).

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing']`.

=== php rules ===

## PHP

- Always use strict typing at the head of a `.php` file: `declare(strict_types=1);`.
- Always use curly braces for control structures, even if it has one line.
- Use PHP 8 constructor property promotion.
- Always use explicit return type declarations and appropriate type hints.
- Add useful PHPDoc blocks, including array shape definitions for arrays when appropriate.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `sail artisan make:` commands to create new files (migrations, controllers, models, etc.).
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input.

### Database
- All database changes **MUST** be done through migration files. Direct database alteration is **FORBIDDEN**.
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries. Avoid `DB::`; prefer `Model::query()`.
- Prevent N+1 query problems by using eager loading.

### Model Creation
- When creating new models, create useful factories and seeders for them too.

### Controllers, Validation & APIs
- **Note:** This project uses a custom architecture. **DO NOT** create `Form Requests` or `API Resources`.
- All validation and API contracts are handled by `spatie/laravel-data` DTOs.
- Business logic belongs in Action classes, not Controllers.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Laravel 12 has a streamlined file structure. `bootstrap/app.php` is used for registering middleware, exceptions, and routing files.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `sail pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `sail pint` without `--dirty` as it will reformat the entire codebase, which is not desired.

=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.
- All tests must be written using Pest. Use `sail artisan make:test --pest <name>`.
- Tests should cover success cases (2xx), client errors (4xx - validation, auth), and server errors (5xx).
- Use descriptive test names (`it('verb noun')`).
- Use the Arrange-Act-Assert pattern.
- Mock external dependencies and services.

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `sail artisan test`.
- To run all tests in a file: `sail artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `sail artisan test --filter=testName`.

### Pest Assertions
- When asserting status codes on a response, use specific methods like `assertForbidden` and `assertNotFound` instead of `assertStatus(403)`.

### Test Data and Mocking
- When creating models for tests, use model factories.
- For Faker, use methods such as `$this->faker->word()` or `fake()->randomDigit()`.
- Use `Pest\Laravel\mock` (or `$this->mock()`) for mocking dependencies.
- Use datasets to simplify tests with duplicated data, especially for validation rules.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- **Note:** This project uses a custom `AuthTestTrait` instead of `actingAs()` and forbids the `RefreshDatabase` trait.

</laravel-boost-guidelines>
