# Codebase Digest: Jedu E-Commerce API

## 1. High-Level Architecture
- **Type:** Headless Laravel E-Commerce Platform - Pure REST API with no traditional web views
- **Interfaces:** Dual API system: Admin (`/api/v1/admin/*`) and Customer (`/api/v1/*`) interfaces with separate authentication guards
- **Core Principle:** Business logic is centralized in Actions/Services consumed by thin controllers for each interface

## 2. Core Technologies
- **PHP Version:** ^8.4
- **Laravel Version:** ^12.0
- **Database:** PostgreSQL with JSONB support
- **Key Packages:**
  - `spatie/laravel-data`: Comprehensive DTO system for type-safe API contracts (v4.15)
  - `spatie/laravel-permission`: Role-based access control for admin operations (v6.18)
  - `spatie/laravel-query-builder`: Advanced API filtering and querying (v6.3)
  - `plank/laravel-mediable`: Media management and file handling (v6.3)
  - `iazaran/smart-cache`: Smart cache facade with configurable invalidation map powering SettingsService and content cache refreshes
  - `spatie/laravel-webhook-client`: External integrations and webhooks (v3.4)
  - `laravel/sanctum`: Dual-guard authentication system (v4.0)
- **Service Layer Architecture:**
  - `SkuGeneratorService`: Automatic SKU generation with pattern-based formatting for product delivery options
  - `ProductQueryService` (`App\Query\ProductQueryService`): Unified product query engine with Typesense-to-database fallback, availability window filters, and deferred relationship constraints for score-aware ordering
  - `CategoryQueryService` (`App\Query\CategoryQueryService`): Category-based product retrieval that reuses the central query engine and batch pricing hydration
  - `GlobalSearchService`: Multi-collection search across products and blog posts with Scout/Typesense integration, union hydration, and automatic SWR-cached suggestions

## 3. Architectural Principles & Patterns (Mandatory for New Code)
- **API Contract:** All API requests and responses MUST use `spatie/laravel-data` DTOs in `app/Data/`. These DTOs are the definitive contract for all API interactions
- **Business Logic:** Business logic MUST be encapsulated in Action Classes within `app/Actions/` organized by interface (Admin/Shop). Controllers MUST remain thin and delegate to Actions
- **Database:** All schema is managed via migration files in `database/migrations/` with PostgreSQL-specific features like JSONB columns
- **API Authentication:** Dual Sanctum guard system - `auth:staff` for Admin API, `auth:user` for Customer API with separate User/Staff models
- **Response Format:** Consistent JSON response structure using custom response macros (success, created, updated, validationErrors, notFound, forbidden)

## 4. Complete Feature Coverage

### Core E-Commerce Features
- **Product Management:** Multi-type products (Course, Seminar, DigitalAsset) with polymorphic relationships, delivery options, and media management
- **Order System:** Complete order lifecycle with items, payments, refunds, and status tracking. Supports configurable provisioning triggers: `any_payment` (auto-provision), `full_payment` (provision when fully paid), `manual_approval` (staff must approve). Customers can cancel pending orders.
- **Discount System:** Advanced promotion engine with complex rules, conditions, and coupon management. Now enforces `usage_limit_total` on promotions.
- **Payment Processing:** Multi-gateway support (wallet, bank transfer, Mellat) with factory pattern implementation. All processors now create per-attempt `PaymentTransaction` records for full audit trail. New `PaymentTransactionReferenceService` for unique sequential reference generation with concurrency safety.
- **Enrolment System:** Student access management for purchased content with lifecycle tracking. Enrollments now fire model events on status changes for `enrolled_count` synchronization.

### Admin Platform Features
- **Staff Management:** Role-based admin users with comprehensive permission system using Spatie Permission
- **Audit System:** Complete action logging with risk assessment, compliance reporting, and suspicious activity detection
- **Content Management:** Categories with hierarchy, media management, and "good for start" recommendations
- **Product Relationships:** Comprehensive related product management with support for related (similar/alternative), cross-sell (frequently bought together), and upsell (premium alternatives) relationships; includes list, filter by type, bulk attach/sync, and delete operations with validation to prevent self-referencing
- **Site CMS:** Modular `App\Http\Controllers\Api\Admin\Content\*` controllers covering header, footer, about us, collaboration content, partners, sliders (with publication status toggles), and homepage blocks backed by reusable DTOs
- **Student Story CMS:** Admin `StudentStoryController` now accepts featured flags plus `categories[]`/`courses[]` associations, while list endpoints expose `filter[course_id]` and `filter[category_id]` for precise moderation of curated testimonials
- **Blog System:** Complete blog management with hierarchical categories, publication workflow, content relationships to educational materials, and automated scheduling
- **Settings Management:** Settings index endpoint plus SmartCache-backed SettingsService with eviction observer to keep responses consistent across the admin and shop surfaces
- **Form Intake:** Admin review workflows for advice requests alongside new collaboration/contact form submissions with attachment handling
- **Review System:** Customer review management with approval workflow and featured selection
- **Wallet System:** User credit management with campaigns, bulk allocations, and transaction tracking
- **File Management:** Public media and private file handling with secure access controls
- **Product Select Options:** Dedicated endpoint for product dropdowns with id, title (short_name), subtitle (slug), and type; supports search across product names, filtering by productable type (course, seminar, digital_asset), and configurable result limits

### Customer Features
- **Authentication:** OTP and password-based login with secure token management
- **Profile Management:** Customer account management and profile updates
- **Course Access:** Enrollment-based access to purchased content
- **Review System:** Customer review submission for products and courses
- **Teacher Profiles:** View detailed instructor profiles with avatar, bio, rate, and social media links; browse teachers associated with specific products
- **Home Page Content:** Dynamic home page block hydration with curated, dynamic, banner, and webinar layouts powered by cached pricing data, block-specific hydration actions, and SWR caching profiles
- **Public CMS Pages:** Read-only endpoints for header, footer, about us, collaboration, contact page, partner listings, sliders, and student stories derived from admin-managed settings
- **Public Course Catalog:** Browse courses, seminars, and digital assets with filtering by fulfillment type, category slug, difficulty level, price range, availability windows, and discount flags driven by the shared product query engine
- **Course Details:** Detailed course information pages with curriculum, pricing options, and teacher information
- **Category Browsing:** Hierarchical category listing and detail pages with product counts
- **Category Products:** Browse products within categories by type (course, seminar, digital asset) with pagination
- **Consultation Requests:** Request educational consultation via phone number submission with rate-limited form handling
- **Cart & Checkout:** Persistent carts for guests and authenticated users (coupon support, capacity validation) feed the checkout pipeline that validates registration window (`registration_start_date`/`registration_end_date`) and availability window (`available_from`/`available_to`) on each item before converting carts into orders and launching wallet, bank-transfer, or Mellat gateway processors with retry + callback verification endpoints
- **Blog & Editorial Content:** Public `/api/v1/shop/blog/*` endpoints provide paginated blog post listings, slug detail retrieval, and category feeds with filtering by featured flag, category slug, and sort order for customer discovery
- **Related Product Recommendations:** `/api/v1/shop/product/{slug}/related/{relation_type}` serves related, cross-sell, and upsell suggestions using `ProductQueryService` + `ProductPriceService` hydration for consistent card responses
- **Student Story Filtering:** Home page student stories now accept `course_slug`, `category_slug`, and `featured_only` filters (with featured fallback) so the storefront can highlight context-aware testimonials without recomputing data client-side

### System Features
- **Multi-tenancy Support:** Vendor-based product organization
- **Academic Terms:** Time-based product organization and scheduling
- **Media Management:** Comprehensive file and image handling with processing
- **SMS Integration:** OTP delivery via IP Panel SMS service
- **API Documentation:** Comprehensive endpoint coverage with DTOs
- **Select Options:** Dropdown data provision for admin interface
- **Content Automation:** Scheduled blog post publication with automated workflow management
- **Smart Cache Invalidation:** Event-driven cache invalidation map with `InvalidationObserver`, `CacheInvalidationService`, `CacheKeysEnum`, and `SettingsService` to keep storefront content fresh
- **Unified Search & Discovery:** Typesense-powered search with PGroonga-backed database fallback, multi-model result hydration, and SWR-cached autosuggest responses
- **Review Aggregation:** Background listener recomputes `review_count` and `average_rating` whenever reviews change for reviewable models
- **Price Indexing System:** Denormalized product_prices table for fast price queries with discount and featured price calculations
  - **Automated Updates:** `UpdateProductPricingJob` for batch updates, `CheckExpiredFeaturedPricesCommand` for scheduled expiry checks
  - **Manual Indexing:** `prices:index-all` command with --missing-only, --sync, and --queue options for maintenance
  - **Query Optimization:** ProductQueryService leverages price index for efficient filtering and sorting
- **Automatic SKU Generation:** SkuGeneratorService creates unique product codes when SKU not manually provided

## 5. Security & Compliance
- **Audit Trail:** All admin actions logged with risk assessment
- **Permission System:** Granular role-based access control
- **Authentication Guards:** Separate authentication for admin and customer interfaces
- **File Security:** Private file access with authorization checks
- **Data Validation:** Comprehensive request validation through DTOs
- **Compliance Reporting:** Automated compliance report generation

## 6. Data Model Completeness
**39 Models Total:** User, Staff, AdminActionLog, Order, OrderItem, Product (polymorphic to Course/Seminar/DigitalAsset), ProductDeliveryOption (now includes `access_days` for content access duration), ProductDeliveryOptionDiscountPrice, ProductPrice (pricing index), Enrollment (now stores `provisioning_data` JSONB for per-provider provisioning state), **Payment** (now with `last_gateway_reference`, `attempt_count`, `last_attempted_at`), **PaymentTransaction** (NEW — per-attempt gateway tracking with full request/response capture), Refund, Review, Category (now with self-referencing parent/children), Categorizable, Teacher, Vendor, Term, DiscountPromotion, DiscountPromotionRule, DiscountCoupon, Wallet, WalletTransaction, WalletCampaign, Setting (secrets encrypted at rest via SettingSecretRedactor), HomePageBlock (now includes `type` field), Slider, StudentStory, CollaborationCarousel, CollaborationRequest, ContactUsRequest, AdviceRequest, SmsLog, BlogCategory, BlogPost (now with `average_rating` for popularity sorting)

## 7. Business Logic Coverage
**100+ Action Classes** (plus 4 integration services, 5 provisioning jobs, and setting encryption commands) organized by domain after the content and form refactor:
- **Admin Actions:** Complete CRUD operations for all entities plus new helpers such as `GetThumbnailUrlAction` and `UpdateSliderStatusAction`, aligning with the Content namespace split and review aggregation workflows; includes `CreateRelatedProductAction` and `DeleteRelatedProductAction` for managing product merchandising relationships; enhanced `CreateTeacherAction` and `UpdateTeacherAction` supporting UUID auto-generation, avatar uploads, and social media links
- **Shop Actions:** Customer-facing operations now include `GetHomePageBlocksListAction`, `GetHomePageBlockAction`, attachment-aware collaboration/contact form submissions, shared file upload handling, and `StoreAdviceRequestAction` for consultation request submissions
- **Auth Actions:** Comprehensive authentication system with OTP and password support
- **Wallet Actions:** Credit management and transaction processing

**Comprehensive Service Layer:**
- **Order Management:** Status tracking and lifecycle management
- **Discount Engine:** Advanced promotion calculation with multiple rule types, conditions, and cart/product-level actions
- **Payment Processing:** Multi-gateway support with factory pattern; all processors now create per-attempt `PaymentTransaction` records with full gateway request/response capture; `PaymentTransactionReferenceService` generates unique sequential references
- **Product Pricing:** Centralized pricing service with hierarchy support (product discounts > featured prices > standard prices) and request-scoped caching
- **Price Indexing:** Denormalized pricing table with `UpdateProductPricingJob` for batch updates and scheduled `CheckExpiredFeaturedPricesCommand` for expiry checks
- **SKU Generation:** Automatic SKU generation via `SkuGeneratorService` with pattern-based formatting
- **Product Querying:** `ProductQueryService` provides fluent interface for complex product filtering with discount, price range, category, and availability filters; enhanced search matching across product names (name, short_name) and productable fields using `whereLike()` for optimized pattern matching
- **Category Querying:** `CategoryQueryService` handles category-based product retrieval with type filtering
- **Content Management:** Dynamic home page content assembly with performance optimization and SmartCache-aware invalidation
- **Settings:** `SettingsService` centralizes cached reads/writes for site-wide configuration with SmartCache, SKIP_MEDIA optimization for integration keys, auto-encryption of secrets on write, auto-decryption on read, and redaction in API responses via `SettingSecretRedactor`
- **Integration Services:** `ImsService` (REST student/enrollment CRUD with PII redaction), `MoodleService` (Web Services user/enrollment/grades/completion/SSO), `SpotPlayerService` (video license provisioning), `BbbService` (BigBlueButton meeting management and join URL generation). All use `SettingsService` for credential resolution with dual-mode config (direct/settings).
- **Provisioning Jobs:** `ProvisionImsEnrollmentJob`, `ProvisionMoodleEnrollmentJob`, `ProvisionSpotPlayerEnrollmentJob`, `ProvisionBbbEnrollmentJob` — each with 3 retries [60s, 180s, 600s] and shared `HandlesProvisioningStatus` trait for provisioning state management. `OrderStatusUpdateListener` dispatches based on delivery method after order completed. `SyncMoodleProgressJob` updates enrollment provisioning_data with Moodle completion/grades.
- **ExternalProvisioningException:** Custom exception class with context array for structured error logging in integration services
- **PaymentTransactionReferenceService:** Unique sequential reference generation with row-locking for concurrent safety
- **Order Provisioning:** Configurable trigger system (`any_payment`, `full_payment`, `manual_approval`) with `ApproveOrderAction` for staff approval flow
- **OTP Management:** Secure verification code handling
- **SMS Service:** Integration with external SMS provider
- **Console Commands:** Automated blog post publication, price indexing with `prices:index-all` (--missing-only, --sync, --queue options), featured price expiry checks with `prices:check-expired-featured` (--dry-run, --queue options), `payments:check-stuck` for detecting abandoned gateway payments, and `settings:encrypt-secrets` (--dry-run) for migrating legacy plaintext integration secrets to encrypted at rest
- **Performance Optimization:** Request-scoped caching service to prevent N+1 queries and duplicate calculations

## 8. API Interface Completeness
**235+ Endpoints** across all domains (including new Moodle SSO, blog popularity sort with related products, category children, product listing capacity/availability filters, settings update with encrypted secrets):
- **Admin API:** Complete platform management with 170+ endpoints including the new Content module for CMS settings, slider status toggles, product relationship management (related, cross-sell, upsell), and the `POST order/{order}/approve` endpoint for staff order approval
- **Customer API:** Profile and course access management with Moodle progress sync triggered on enrollment detail view (rate-limited 5-min per enrollment)
- **Shop Public API:** Modular endpoints for home page blocks, sliders, partners, header/footer, CMS pages, teacher profiles, and rate-limited contact/collaboration/advice request form submissions
- **Course Catalog API:** Public course listing with advanced filtering (search, category, level, price range, capacity (nearing/full), availability status (past/upcoming/ongoing), discounts, sort by `capacity_utilization`) and detailed course pages. ProductCardData enriched with `registration_status`, `delivery_type`, and `teachers` arrays.
- **Category API:** Category listing, detail pages, and category-based product browsing by type with pagination
- **Teacher API:** Teacher profile display and product-specific teacher listings
- **Authentication:** Dual system for both admin and customer interfaces
- **File Management:** Secure media and private file handling
- **Select Options:** Dropdown data for admin interface including dedicated product select-options endpoint with search, type filtering, and configurable limits
- **Blog Management:** Full CRUD operations for blog categories and posts with publication workflow, `sortBy=popularity` (by `average_rating`) on public listing, related products in post detail, and thumbnail scoped to `cover` media tag
- **Moodle SSO:** `GET /api/v1/shop/moodle/sso/{order:increment_id}` generates auto-login URL for enrolled users
- **Category API:** Categories now expose `children` hierarchy in `CategoryCardData` for recursive navigation

### System Features (continued)
- **Provisioning System:** 4 integration services (IMS REST, Moodle Web Services, SpotPlayer License, BigBlueButton) each with dedicated queued provisioning jobs (3 retries, exponential backoff). `OrderStatusUpdateListener` dispatches jobs after `OrderStatusUpdatedEvent`. `SyncMoodleProgressJob` syncs Moodle progress on enrollment view.
- **Settings Encryption:** Secrets in integration configs (API keys, tokens, passwords) encrypted at rest via `Crypt::encryptString()`, auto-decrypted on read, redacted in API responses via `SettingSecretRedactor`. `SKIP_MEDIA` optimization skips `witImages()` for integration keys.
- **CORS:** Schema-based allowed origins with credentials support via dedicated `config/cors.php`

## 9. Digest Index
- **[Data Models & Relationships](./DIGEST_DATA_MODELS.md)** - Complete coverage of all 39 models with relationships including PaymentTransaction, teacher profiles, provisioning trigger configuration, Category parent/children hierarchy, ProductDeliveryOption access_days, Enrollment provisioning_data, BlogPost average_rating, and new product enums (AvailabilityStatusEnum, ProductRegistrationStatusEnum, ProductDeliveryStatusEnum, DeliveryMethodEnum, FulfillmentTypeEnum)
- **[Core Business Logic (Actions/Services)](./DIGEST_CORE_LOGIC.md)** - Complete coverage of 100+ Action classes, comprehensive services (now including Integration services ImsService/MoodleService/SpotPlayerService/BbbService, PaymentTransactionReferenceService, SettingSecretRedactor), provisioning jobs (4 providers + SyncMoodleProgressJob + OrderStatusUpdateListener), ProductQueryService capacity filters and availability sort, SettingsService encrypt/decrypt/SKIP_MEDIA/redact, and console commands (now including CheckStuckPaymentsCommand + EncryptSettingSecretsCommand)
- **[API Interfaces & Endpoints](./DIGEST_API_INTERFACES.md)** - Complete coverage of 235+ API endpoints organized by domain including order approval, order cancellation, registration/availability window validation, product listing capacity filters (nearing capacity, availability status, capacity_utilization sort), blog popularity sort + related products, category children hierarchy, Moodle SSO endpoint, Settings update with encrypted secrets, CORS configuration, and ProductCardData enrichment (registration_status, delivery_type, teachers)

```
