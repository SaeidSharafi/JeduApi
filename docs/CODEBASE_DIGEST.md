# Codebase Digest: Jedu E-Commerce API

## 1. High-Level Architecture
- **Type:** Headless Laravel E-Commerce Platform - Pure REST API with no traditional web views
- **Interfaces:** Dual API system: Admin (`/api/v1/admin/*`) and Customer (`/api/v1/*`) interfaces with separate authentication guards
- **Core Principle:** Business logic is centralized in Actions/Services consumed by thin controllers for each interface

## 2. Core Technologies
- **PHP Version:** ^8.2
- **Laravel Version:** ^12.0
- **Database:** PostgreSQL with JSONB support
- **Key Packages:**
  - `spatie/laravel-data`: Comprehensive DTO system for type-safe API contracts (v4.15)
  - `spatie/laravel-permission`: Role-based access control for admin operations (v6.18)
  - `spatie/laravel-query-builder`: Advanced API filtering and querying (v6.3)
  - `plank/laravel-mediable`: Media management and file handling (v6.3)
  - `spatie/laravel-webhook-client`: External integrations and webhooks (v3.4)
  - `laravel/sanctum`: Dual-guard authentication system (v4.0)

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
- **Blog System:** Complete blog management with hierarchical categories, publication workflow, content relationships to educational materials, and automated scheduling
- **Settings Management:** Application configuration including contact info, about us, headers, footers, sliders, and homepage blocks
- **Review System:** Customer review management with approval workflow and featured selection
- **Wallet System:** User credit management with campaigns, bulk allocations, and transaction tracking
- **File Management:** Public media and private file handling with secure access controls

### Customer Features
- **Authentication:** OTP and password-based login with secure token management
- **Profile Management:** Customer account management and profile updates
- **Course Access:** Enrolment-based access to purchased content
- **Review System:** Customer review submission for products and courses

### System Features
- **Multi-tenancy Support:** Vendor-based product organization
- **Academic Terms:** Time-based product organization and scheduling
- **Media Management:** Comprehensive file and image handling with processing
- **SMS Integration:** OTP delivery via IP Panel SMS service
- **API Documentation:** Comprehensive endpoint coverage with DTOs
- **Select Options:** Dropdown data provision for admin interface
- **Content Automation:** Scheduled blog post publication with automated workflow management

## 5. Security & Compliance
- **Audit Trail:** All admin actions logged with risk assessment
- **Permission System:** Granular role-based access control
- **Authentication Guards:** Separate authentication for admin and customer interfaces
- **File Security:** Private file access with authorization checks
- **Data Validation:** Comprehensive request validation through DTOs
- **Compliance Reporting:** Automated compliance report generation

## 6. Data Model Completeness
**36 Models Total:** User, Staff, AdminActionLog, Order, OrderItem, Product (polymorphic to Course/Seminar/DigitalAsset), ProductDeliveryOption, ProductDeliveryOptionDiscountPrice, Enrolment, Payment, Refund, Review, Category, Categorizable, Teacher, Vendor, Term, DiscountPromotion, DiscountPromotionRule, DiscountCoupon, Wallet, WalletTransaction, WalletCampaign, Setting, HomePageBlock, Slider, StudentStory, CollaborationCarousel, CollaborationRequest, ContactUsRequest, SmsLog, BlogCategory, BlogPost

## 7. Business Logic Coverage
**96+ Action Classes** organized by domain (after adding blog management actions):
- **Admin Actions:** Complete CRUD operations for all entities with business logic including new blog management
- **Shop Actions:** Customer-facing operations and profile management
- **Auth Actions:** Comprehensive authentication system with OTP and password support
- **Wallet Actions:** Credit management and transaction processing

**Comprehensive Service Layer:**
- **Order Management:** Status tracking and lifecycle management
- **Discount Engine:** Advanced promotion calculation with multiple rule types, conditions, and cart/product-level actions
- **Payment Processing:** Multi-gateway support with factory pattern
- **OTP Management:** Secure verification code handling
- **SMS Service:** Integration with external SMS provider
- **Console Commands:** Automated blog post publication and system maintenance
- **OTP Management:** Secure verification code handling
- **SMS Service:** Integration with external SMS provider

## 8. API Interface Completeness
**210+ Endpoints** across all domains:
- **Admin API:** Complete platform management with 160+ endpoints including blog management
- **Customer API:** Profile and course access management
- **Authentication:** Dual system for both admin and customer interfaces
- **File Management:** Secure media and private file handling
- **Select Options:** Dropdown data for admin interface
- **Blog Management:** Full CRUD operations for blog categories and posts with publication workflow

## 9. Digest Index
- **[Data Models & Relationships](./DIGEST_DATA_MODELS.md)** - Complete coverage of all 36 models with relationships including new blog system
- **[Core Business Logic (Actions/Services)](./DIGEST_CORE_LOGIC.md)** - Complete coverage of 96+ Action classes, comprehensive services, and console commands
- **[API Interfaces & Endpoints](./DIGEST_API_INTERFACES.md)** - Complete coverage of 210+ API endpoints organized by domain including blog management
