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
  - `ProductQueryService`: Fluent query builder for product filtering, search, and sorting with price range support
  - `CategoryQueryService`: Category-based product retrieval with type filtering

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
- **Site CMS:** Modular `App\Http\Controllers\Api\Admin\Content\*` controllers covering header, footer, about us, collaboration content, partners, sliders (with publication status toggles), and homepage blocks backed by reusable DTOs
- **Blog System:** Complete blog management with hierarchical categories, publication workflow, content relationships to educational materials, and automated scheduling
- **Settings Management:** Settings index endpoint plus SmartCache-backed SettingsService with eviction observer to keep responses consistent across the admin and shop surfaces
- **Form Intake:** Admin review workflows for advice requests alongside new collaboration/contact form submissions with attachment handling
- **Review System:** Customer review management with approval workflow and featured selection
- **Wallet System:** User credit management with campaigns, bulk allocations, and transaction tracking
- **File Management:** Public media and private file handling with secure access controls

### Customer Features
- **Authentication:** OTP and password-based login with secure token management
- **Profile Management:** Customer account management and profile updates
- **Course Access:** Enrollment-based access to purchased content
- **Review System:** Customer review submission for products and courses
- **Home Page Content:** Dynamic home page block hydration with curated, dynamic, banner, and webinar layouts powered by cached pricing data and block-specific hydration actions
- **Public CMS Pages:** Read-only endpoints for header, footer, about us, collaboration, contact page, partner listings, sliders, and student stories derived from admin-managed settings
- **Public Course Catalog:** Browse courses with filtering by fulfillment type, category, level, price range, and discount availability
- **Course Details:** Detailed course information pages with curriculum, pricing options, and teacher information
- **Category Browsing:** Hierarchical category listing and detail pages with product counts
- **Category Products:** Browse products within categories by type (course, seminar, digital asset) with pagination

### System Features
- **Multi-tenancy Support:** Vendor-based product organization
- **Academic Terms:** Time-based product organization and scheduling
- **Media Management:** Comprehensive file and image handling with processing
- **SMS Integration:** OTP delivery via IP Panel SMS service
- **API Documentation:** Comprehensive endpoint coverage with DTOs
- **Select Options:** Dropdown data provision for admin interface
- **Content Automation:** Scheduled blog post publication with automated workflow management
- **Smart Cache Invalidation:** Event-driven cache invalidation map with `InvalidationObserver`, `CacheKeysEnum`, and `SettingsService` to keep storefront content fresh
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
**37 Models Total:** User, Staff, AdminActionLog, Order, OrderItem, Product (polymorphic to Course/Seminar/DigitalAsset), ProductDeliveryOption, ProductDeliveryOptionDiscountPrice, ProductPrice (pricing index), Enrollment, Payment, Refund, Review, Category, Categorizable, Teacher, Vendor, Term, DiscountPromotion, DiscountPromotionRule, DiscountCoupon, Wallet, WalletTransaction, WalletCampaign, Setting, HomePageBlock, Slider, StudentStory, CollaborationCarousel, CollaborationRequest, ContactUsRequest, SmsLog, BlogCategory, BlogPost

## 7. Business Logic Coverage
**100+ Action Classes** organized by domain after the content and form refactor:
- **Admin Actions:** Complete CRUD operations for all entities plus new helpers such as `GetThumbnailUrlAction` and `UpdateSliderStatusAction`, aligning with the Content namespace split and review aggregation workflows
- **Shop Actions:** Customer-facing operations now include `GetHomePageBlocksListAction`, `GetHomePageBlockAction`, attachment-aware collaboration/contact form submissions, and shared file upload handling
- **Auth Actions:** Comprehensive authentication system with OTP and password support
- **Wallet Actions:** Credit management and transaction processing

**Comprehensive Service Layer:**
- **Order Management:** Status tracking and lifecycle management
- **Discount Engine:** Advanced promotion calculation with multiple rule types, conditions, and cart/product-level actions
- **Payment Processing:** Multi-gateway support with factory pattern
- **Product Pricing:** Centralized pricing service with hierarchy support (product discounts > featured prices > standard prices) and request-scoped caching
- **Price Indexing:** Denormalized pricing table with `UpdateProductPricingJob` for batch updates and scheduled `CheckExpiredFeaturedPricesCommand` for expiry checks
- **SKU Generation:** Automatic SKU generation via `SkuGeneratorService` with pattern-based formatting
- **Product Querying:** `ProductQueryService` provides fluent interface for complex product filtering with discount, price range, category, and availability filters
- **Category Querying:** `CategoryQueryService` handles category-based product retrieval with type filtering
- **Content Management:** Dynamic home page content assembly with performance optimization and SmartCache-aware invalidation
- **Settings:** `SettingsService` centralizes cached reads/writes for site-wide configuration
- **OTP Management:** Secure verification code handling
- **SMS Service:** Integration with external SMS provider
- **Console Commands:** Automated blog post publication, price indexing with `prices:index-all` (--missing-only, --sync, --queue options), and featured price expiry checks with `prices:check-expired-featured` (--dry-run, --queue options)
- **Performance Optimization:** Request-scoped caching service to prevent N+1 queries and duplicate calculations

## 8. API Interface Completeness
**220+ Endpoints** across all domains:
- **Admin API:** Complete platform management with 160+ endpoints including the new Content module for CMS settings and slider status toggles
- **Customer API:** Profile and course access management
- **Shop Public API:** Modular endpoints for home page blocks, sliders, partners, header/footer, CMS pages, and rate-limited contact/collaboration form submissions
- **Course Catalog API:** Public course listing with advanced filtering (search, category, level, price range, discounts) and detailed course pages
- **Category API:** Category listing, detail pages, and category-based product browsing by type with pagination
- **Authentication:** Dual system for both admin and customer interfaces
- **File Management:** Secure media and private file handling
- **Select Options:** Dropdown data for admin interface
- **Blog Management:** Full CRUD operations for blog categories and posts with publication workflow

## 9. Digest Index
- **[Data Models & Relationships](./DIGEST_DATA_MODELS.md)** - Complete coverage of all 36 models with relationships including new blog system
- **[Core Business Logic (Actions/Services)](./DIGEST_CORE_LOGIC.md)** - Complete coverage of 96+ Action classes, comprehensive services, and console commands
- **[API Interfaces & Endpoints](./DIGEST_API_INTERFACES.md)** - Complete coverage of 210+ API endpoints organized by domain including blog management
