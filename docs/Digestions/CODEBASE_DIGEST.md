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
- **Order System:** Complete order lifecycle with items, payments, refunds, and status tracking
- **Discount System:** Advanced promotion engine with complex rules, conditions, and coupon management
- **Payment Processing:** Multi-gateway support (wallet, bank transfer) with factory pattern implementation
- **Enrolment System:** Student access management for purchased content with lifecycle tracking

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
- **Cart & Checkout:** Persistent carts for guests and authenticated users (coupon support, capacity validation) feed the checkout pipeline that converts carts into orders and launches wallet, bank-transfer, or Mellat gateway processors with retry + callback verification endpoints
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
**38 Models Total:** User, Staff, AdminActionLog, Order, OrderItem, Product (polymorphic to Course/Seminar/DigitalAsset), ProductDeliveryOption, ProductDeliveryOptionDiscountPrice, ProductPrice (pricing index), Enrollment, Payment, Refund, Review, Category, Categorizable, Teacher, Vendor, Term, DiscountPromotion, DiscountPromotionRule, DiscountCoupon, Wallet, WalletTransaction, WalletCampaign, Setting, HomePageBlock, Slider, StudentStory, CollaborationCarousel, CollaborationRequest, ContactUsRequest, AdviceRequest, SmsLog, BlogCategory, BlogPost

## 7. Business Logic Coverage
**100+ Action Classes** organized by domain after the content and form refactor:
- **Admin Actions:** Complete CRUD operations for all entities plus new helpers such as `GetThumbnailUrlAction` and `UpdateSliderStatusAction`, aligning with the Content namespace split and review aggregation workflows; includes `CreateRelatedProductAction` and `DeleteRelatedProductAction` for managing product merchandising relationships; enhanced `CreateTeacherAction` and `UpdateTeacherAction` supporting UUID auto-generation, avatar uploads, and social media links
- **Shop Actions:** Customer-facing operations now include `GetHomePageBlocksListAction`, `GetHomePageBlockAction`, attachment-aware collaboration/contact form submissions, shared file upload handling, and `StoreAdviceRequestAction` for consultation request submissions
- **Auth Actions:** Comprehensive authentication system with OTP and password support
- **Wallet Actions:** Credit management and transaction processing

**Comprehensive Service Layer:**
- **Order Management:** Status tracking and lifecycle management
- **Discount Engine:** Advanced promotion calculation with multiple rule types, conditions, and cart/product-level actions
- **Payment Processing:** Multi-gateway support with factory pattern
- **Product Pricing:** Centralized pricing service with hierarchy support (product discounts > featured prices > standard prices) and request-scoped caching
- **Price Indexing:** Denormalized pricing table with `UpdateProductPricingJob` for batch updates and scheduled `CheckExpiredFeaturedPricesCommand` for expiry checks
- **SKU Generation:** Automatic SKU generation via `SkuGeneratorService` with pattern-based formatting
- **Product Querying:** `ProductQueryService` provides fluent interface for complex product filtering with discount, price range, category, and availability filters; enhanced search matching across product names (name, short_name) and productable fields using `whereLike()` for optimized pattern matching
- **Category Querying:** `CategoryQueryService` handles category-based product retrieval with type filtering
- **Content Management:** Dynamic home page content assembly with performance optimization and SmartCache-aware invalidation
- **Settings:** `SettingsService` centralizes cached reads/writes for site-wide configuration
- **OTP Management:** Secure verification code handling
- **SMS Service:** Integration with external SMS provider
- **Console Commands:** Automated blog post publication, price indexing with `prices:index-all` (--missing-only, --sync, --queue options), and featured price expiry checks with `prices:check-expired-featured` (--dry-run, --queue options)
- **Performance Optimization:** Request-scoped caching service to prevent N+1 queries and duplicate calculations

## 8. API Interface Completeness
**225+ Endpoints** across all domains:
- **Admin API:** Complete platform management with 165+ endpoints including the new Content module for CMS settings, slider status toggles, and product relationship management (related, cross-sell, upsell)
- **Customer API:** Profile and course access management
- **Shop Public API:** Modular endpoints for home page blocks, sliders, partners, header/footer, CMS pages, teacher profiles, and rate-limited contact/collaboration/advice request form submissions
- **Course Catalog API:** Public course listing with advanced filtering (search, category, level, price range, discounts) and detailed course pages
- **Category API:** Category listing, detail pages, and category-based product browsing by type with pagination
- **Teacher API:** Teacher profile display and product-specific teacher listings
- **Authentication:** Dual system for both admin and customer interfaces
- **File Management:** Secure media and private file handling
- **Select Options:** Dropdown data for admin interface including dedicated product select-options endpoint with search, type filtering, and configurable limits
- **Blog Management:** Full CRUD operations for blog categories and posts with publication workflow

## 9. Digest Index
- **[Data Models & Relationships](./DIGEST_DATA_MODELS.md)** - Complete coverage of all 38 models with relationships including teacher profiles and advice request system
- **[Core Business Logic (Actions/Services)](./DIGEST_CORE_LOGIC.md)** - Complete coverage of 100+ Action classes, comprehensive services, and console commands
- **[API Interfaces & Endpoints](./DIGEST_API_INTERFACES.md)** - Complete coverage of 220+ API endpoints organized by domain including teacher and advice request endpoints

```
