# Digest: Data Models & Relationships

### User (`app/Models/User.php`)
- **Purpose:** Customer accounts for the e-commerce platform
- **Key Fields:** `uuid`, `first_name`, `last_name`, `email`, `phone`, `password`, `civil_id`, `date_of_birth`, `gender`, `education_level`
- **Relationships:** 
  - `hasOne(Teacher::class)` - teacherData
  - `hasMany(Enrollment::class, 'customer_id')` - enrollments
  - `hasOne(Wallet::class)` - wallet
  - `hasMany(Review::class)` - reviews
  - `hasOne(Cart::class)` - cart
  - `hasMany(Order::class, 'customer_id')` - orders
- **Guard:** `user` (Sanctum authentication)

### Staff (`app/Models/Staff.php`)
- **Purpose:** Admin users with role-based permissions
- **Key Fields:** `name`, `email`, `password`, permission-related fields
- **Relationships:** 
  - Uses Spatie Permission package for roles/permissions
  - `hasMany(AdminActionLog::class, 'admin_id')` - actionLogs
- **Guard:** `staff` (Sanctum authentication)

### AdminActionLog (`app/Models/AdminActionLog.php`)
- **Purpose:** Audit trail for admin actions and compliance monitoring
- **Key Fields:** `admin_id`, `action_type`, `resource_type`, `resource_id`, `route_name`, `http_method`, `request_data`, `response_status`, `ip_address`, `user_agent`, `session_id`, `risk_level`, `metadata`
- **Relationships:**
  - `belongsTo(Staff::class, 'admin_id')` - admin
  - `morphTo()` - resource (polymorphic to any audited model)
- **Special Features:** Risk level categorization, wallet action detection, compliance reporting

### Order (`app/Models/Order.php`)
- **Purpose:** Sales transaction records implementing WalletTransactionSourceableContract
- **Key Fields:** `increment_id`, `status`, `customer_id`, `total_item_count`, `subtotal`, `discount_amount`, `grand_total`, `applied_coupon_code`
- **Relationships:**
  - `hasMany(OrderItem::class)` - items
  - `hasMany(Payment::class)` - payments
  - `hasMany(Enrollment::class, 'order_id')` - enrollments
  - `belongsTo(User::class, 'customer_id')` - customer
- **Special Features:** Auto-incrementing order numbers generated via `OrderIncrementIdService` (transaction-safe), payment status calculations

### Product (`app/Models/Product.php`)
- **Purpose:** Sellable instances of educational content with polymorphic relationships
- **Key Fields:** `vendor_id`, `productable_id`, `productable_type`, `term_id`, `status`, `is_visible`, `short_name`, `name`, `slug`, `short_description`, `is_featured`, `price_data_cache`, `details_json`
- **Relationships:**
  - `morphTo()` - productable (Course, Seminar, DigitalAsset)
  - `belongsTo(Vendor::class)` - vendor
  - `belongsTo(Term::class)` - term
  - `hasMany(ProductDeliveryOption::class)` - productDeliveryOptions
  - `hasManyThrough(OrderItem::class, ProductDeliveryOption::class)` - orderItems
  - `belongsToMany(self::class, 'related_products', 'product_id', 'related_product_id')` - relatedProducts (all types)
  - `relatedProductsOfType()` - related products filtered by RELATED type
  - `crossSellProducts()` - related products filtered by CROSS_SELL type
  - `upsellProducts()` - related products filtered by UPSELL type
- **Traits:** Uses `HasCategories`, `HasFactory`, and `Searchable` for taxonomy tagging, database seeding, and Scout/Typesense indexing
- **Special Features:** Publication-aware scopes (`active*` helpers) combine status, visibility, and availability checks; SmartCache-backed price snapshots in `price_data_cache`; enum-backed casting for `status` with JSON casting on cached fields; supports product relationships (related, cross-sell, upsell) via pivot table with `relation_type` column; search index payload captures availability windows, discount flags, and scores for Typesense and PGroonga powered relevance ordering

### Course (`app/Models/Course.php`)
- **Purpose:** Educational course definitions and blueprints
- **Key Fields:** `slug`, `thumbnail_url`, `full_name`, `short_name`, `description`, `duration`, `difficulty_level`, `career_prospects_text`, `curriculum_summary_text`, `outcomes_json`, `default_teacher_info`, `provides_certificate`, `faq`, `additional_info`, `properties`, review aggregates (`review_count`, `average_rating`), `meta_title`, `meta_description`, `meta_keywords`, `status`
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - `morphToMany(DigitalAsset::class, 'assetable')` - digitalAssets
  - `morphToMany(BlogPost::class, 'productable', 'blog_post_productables')` - blogPosts
  - `morphToMany(Category::class, 'categorizable', 'categorizables')` - categories
- **Traits:** Uses `HasMedia`, `HasReview`, `IsProductable`, and `Mediable` to centralize media handling, review aggregation (auto-maintained `review_count`/`average_rating`), and polymorphic product binding
- **Special Features:** Implements `ProductableContract` and `ReviewableContract`; participates in review aggregation events to keep cached review metrics synchronized; enum-backed casting for publication status and difficulty level; exposes course FAQs and certificate availability for storefront detail payloads

### Seminar (`app/Models/Seminar.php`)
- **Purpose:** One-off educational events
- **Key Fields:** `full_name`, `short_name`, `subtitle`, `slug`, `thumbnail_url`, `description`, `curriculum_summary_text`, `outcomes_json`, `target_audience`, `prerequisites`, `promo_video_external_url`, `estimated_duration_desc`, `difficulty_level`, `provides_certificate`, `faq`, `keywords`, review aggregates (`review_count`, `average_rating`), `meta_title`, `meta_description`, `meta_keywords`, `status`
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - `morphToMany(BlogPost::class, 'productable', 'blog_post_productables')` - blogPosts
- **Traits:** Combines `HasAssets`, `HasAuditor`, `HasCategories`, `HasMedia`, `HasReview`, `IsProductable`, and `Mediable` to manage attached resources, audit data, categories, and review aggregates
- **Special Features:** Curriculum structure replaced `learning_objectives` with `curriculum_summary_text` (text summary) and `outcomes_json` (structured learning outcomes array) for better content organization; difficulty level now aligns with `CourseDifficultyLevelEnum` for catalog-wide filtering

### DigitalAsset (`app/Models/DigitalAsset.php`)
- **Purpose:** Standalone digital products (PDFs, videos, etc.)
- **Key Fields:** `short_name`, `full_name`, `slug`, `thumbnail_url`, `description`, `version`, `page_count`, `duration_seconds`, `is_attachable_to_course`, `difficulty_level`, `provides_certificate`, `faq`, review aggregates (`review_count`, `average_rating`), `keywords`, `meta_title`, `meta_description`, `meta_keywords`, `published_at`, `status`
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `morphToMany(Category::class, 'categorizable', 'categorizables')` - categories
  - `morphedByMany(Course::class, 'assetable')` - courses
- **Traits:** Uses `HasMedia`, `HasReview`, `IsProductable`, and `Mediable` for media, review aggregation, and polymorphic bindings
- **Special Features:** Name split into `short_name` (max 100 chars) and `full_name` (max 191 chars) for display flexibility; difficulty level, FAQs, and certificate availability mirror course semantics for unified storefront filtering

### ProductDeliveryOption (`app/Models/ProductDeliveryOption.php`)
- **Purpose:** Specific purchase/delivery methods per product with pricing
- **Key Fields:** `uuid` (UUID v7 auto-generated), `sku` (optional, auto-generated if not provided), `name`, `price`, `capacity`, `enrolled_count`, `status`, `fulfillment_type`, `delivery_method`, `is_prepayment_available`, `prepayment_amount`, `is_featured`, `featured_price`, `featured_price_start_date`, `featured_price_end_date`, `registration_start_date`, `registration_end_date`, `available_from`, `available_to`, `details_json`
- **Relationships:**
  - `belongsTo(Product::class)` - product
  - `hasMany(ProductDeliveryOptionDiscountPrice::class)` - discountPrices
  - `belongsToMany(Teacher::class, 'product_delivery_option_teacher')` - teachers
  - `hasMany(Enrollment::class, 'product_delivery_option_id')` - enrollments
  - `hasMany(OrderItem::class)` - orderItems
- **Special Features:** UUID for external references, SKU auto-generation via `SkuGeneratorService` when not provided, capacity tracking backed by the persisted `enrolled_count` column (no more runtime `withCount`), and a `discountPrice` accessor that evaluates active discount windows using `starts_at`/`ends_at` timestamps on `ProductDeliveryOptionDiscountPrice`

### Cart (`app/Models/Cart.php`)
- **Purpose:** Persistent shopping carts for both authenticated customers and guest sessions
- **Key Fields:** `user_id` (nullable), `guest_token` (UUID), `applied_coupon_code`
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `hasMany(CartItem::class)` - items
- **Special Features:** Supports guest checkout via `guest_token`, enables post-login cart merges, and stores coupon context at the cart level

### CartItem (`app/Models/CartItem.php`)
- **Purpose:** Itemized cart entries representing specific `ProductDeliveryOption` selections
- **Key Fields:** `cart_id`, `product_delivery_option_id`, `payment_type`, `quantity`
- **Relationships:**
  - `belongsTo(Cart::class)` - cart
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
- **Special Features:** Casts `payment_type` to `OrderItemPaymentTypeEnum`, enforces uniqueness per cart/delivery option, and tracks multi-seat `quantity`

### ProductDeliveryOptionDiscountPrice (`app/Models/ProductDeliveryOptionDiscountPrice.php`)
 - **Key Fields:** `product_delivery_option_id`, `discount_promotion_id`, `discounted_price`, `starts_at`, `ends_at`, discount metadata
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `belongsTo(DiscountPromotion::class)` - discountPromotion

### OrderItem (`app/Models/OrderItem.php`)
- **Purpose:** Individual line items within orders
- **Key Fields:** `order_id`, `product_delivery_option_id`, `qty_ordered`, `status`, pricing fields
- **Relationships:**
  - `belongsTo(Order::class)` - order
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `hasOne(Enrollment::class)` - enrollment
  - `hasMany(Refund::class)` - refunds

### Enrollment (`app/Models/Enrollment.php`)
- **Purpose:** Student access records linking customers to purchased delivery options
- **Key Fields:** `uuid`, `order_id`, `order_item_id`, `customer_id`, `product_delivery_option_id`, `enrollment_status`, `access_start_date`, `access_end_date`, `external_enrollment_id`, `provisioning_data`
- **Relationships:**
  - `belongsTo(User::class, 'customer_id')` - customer
  - `belongsTo(Order::class)` - order
  - `belongsTo(OrderItem::class)` - orderItem
  - `belongsTo(ProductDeliveryOption::class, 'product_delivery_option_id')` - productDeliveryOption
  - `hasOneThrough(Product::class, ProductDeliveryOption::class)` - product
- **Special Features:** UUID (`uuid7`) generation on create for external references, enum-backed `enrollment_status`, date casting for access window, JSON provisioning payloads, and dispatches `EnrollmentStatusChanged` on save/delete to keep projections synchronized

### Payment (`app/Models/Payment.php`)
- **Purpose:** Financial transaction handling
- **Key Fields:** `uuid`, `order_id`, `amount`, `method`, `status`, payment gateway details
- **Relationships:** 
  - `belongsTo(Order::class)` - order
  - `hasMany(Refund::class)` - refunds
- **Special Features:** Uses `HasUuids` for globally unique references and enum-casts `method` to `PaymentMethodEnum` for processor routing

### Refund (`app/Models/Refund.php`)
- **Purpose:** Refund transaction records
- **Key Fields:** Refund amounts, status, reasoning
- **Relationships:** 
  - `belongsTo(OrderItem::class)` - orderItem
  - `belongsTo(Payment::class)` - payment

### Review (`app/Models/Review.php`)
- **Purpose:** Customer review system for products and courses
- **Key Fields:** `user_id`, `reviewable_type`, `reviewable_id`, `rating`, `title`, `comment`, `status`, `is_featured`
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `morphTo()` - reviewable (Product, Course, Seminar, DigitalAsset)
- **Special Features:** Rating system, featured reviews, approval workflow

### Category (`app/Models/Category.php`)
- **Purpose:** Hierarchical product organization
- **Key Fields:** `name`, `parent_id`, hierarchical structure, `is_good_for_start`
- **Relationships:** 
  - Self-referencing hierarchy
  - Many-to-many with categorizable models
  - Media attachments for icons and images
- **Special Features:** "Good for Start" flagging, media management

### Categorizable (`app/Models/Categorizable.php`)
- **Purpose:** Pivot model for polymorphic category relationships
- **Key Fields:** `category_id`, `categorizable_type`, `categorizable_id`
- **Relationships:** Connects categories to various models

### RelatedProducts (Pivot Table: `related_products`)
- **Purpose:** Many-to-many self-referential relationships between products for merchandising (related, cross-sell, upsell)
- **Key Fields:** `id`, `product_id`, `related_product_id`, `relation_type`, `created_at`, `updated_at`
- **Relationship Types:** Uses `RelationTypeEnum` with values: `related` (similar/alternative products), `cross_sell` (frequently bought together), `upsell` (premium alternatives)
- **Relationships:** 
  - `product_id` FK to `products(id)` CASCADE
  - `related_product_id` FK to `products(id)` CASCADE
- **Special Features:** Unique constraint on (`product_id`, `related_product_id`, `relation_type`) prevents duplicates; indexed on `product_id` and `relation_type` for efficient queries; supports bulk attach/sync operations through `CreateRelatedProductAction`

### Teacher (`app/Models/Teacher.php`)
- **Purpose:** Instructor profiles with rich metadata and portfolio
- **Key Fields:** `uuid` (UUID v7 auto-generated), `first_name`, `last_name`, `bio`, `avatar_url`, `rate`, `email`, `phone`, `gender`, `social_links` (JSON)
- **Relationships:** 
  - `belongsToMany(ProductDeliveryOption::class)` - productDeliveryOptions (pivot: product_delivery_option_teacher)
  - `belongsTo(User::class)` - user
- **Special Features:** UUID auto-generation on create via boot method, avatar support for profile images, structured social media links in JSON format, rate field for pricing/expertise level, enum-backed gender casting

### Vendor (`app/Models/Vendor.php`)
- **Purpose:** Internal departments/external entities
- **Key Fields:** Vendor information and business details
- **Relationships:** `hasMany(Product::class)` - products

### Term (`app/Models/Term.php`)
- **Purpose:** Academic terms and scheduling periods
- **Key Fields:** Term dates, names, academic periods
- **Relationships:** `hasMany(Product::class)` - products

### DiscountPromotion (`app/Models/DiscountPromotion.php`)
- **Purpose:** Advanced discount/coupon system
- **Key Fields:** Discount rules, conditions, and actions
- **Relationships:** 
  - `hasMany(DiscountPromotionRule::class)` - rules
  - `hasMany(DiscountCoupon::class)` - coupons
  - Complex rule-based discount system with conditions and actions

### DiscountPromotionRule (`app/Models/DiscountPromotionRule.php`)
- **Purpose:** Individual rules within discount promotions
- **Key Fields:** Rule conditions, operators, values, rule types
- **Relationships:** `belongsTo(DiscountPromotion::class)` - discountPromotion

### DiscountCoupon (`app/Models/DiscountCoupon.php`)
- **Purpose:** Coupon code management for discount promotions
- **Key Fields:** `code`, `usage_limit`, `used_count`, coupon metadata
- **Relationships:** `belongsTo(DiscountPromotion::class)` - discountPromotion

### Wallet (`app/Models/Wallet.php`)
- **Purpose:** User wallet system for credits and transactions
- **Key Fields:** `user_id`, `balance`, wallet metadata
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `hasMany(WalletTransaction::class)` - transactions

### WalletTransaction (`app/Models/WalletTransaction.php`)
- **Purpose:** Individual wallet transaction records
- **Key Fields:** Transaction amounts, types, source tracking
- **Relationships:** 
  - `belongsTo(Wallet::class)` - wallet
  - Polymorphic source tracking

### ProductPrice (`app/Models/ProductPrice.php`)
- **Purpose:** Precomputed pricing index for fast product price lookups across the storefront
- **Key Fields:** `product_id`, `min_price`, `max_price`, `min_original_price`, `max_original_price`, `has_discount`, `has_featured_price`, `has_prepayment`, `discount_percentage`, `highest_discount_amount`
- **Relationships:**
  - `belongsTo(Product::class)` - product
- **Special Features:** Index table populated by `UpdateProductPricingJob` and maintained by `prices:index-all` command; provides efficient price range queries via `priceRange()`, discount filtering via `withDiscount()` and `withFeaturedPrice()` scopes; enables fast "products with discounts" queries without joins
- **Scopes:** `withDiscount()`, `withFeaturedPrice()`, `priceRange($min, $max)`
- **Methods:** `hasActiveDiscount()`, `isSinglePrice()`, `getDiscountAmount()`, `getPriceRange()`, `getEffectiveMinPrice()`

### WalletCampaign (`app/Models/WalletCampaign.php`)
- **Purpose:** Bulk wallet credit campaigns and promotions
- **Key Fields:** Campaign details, allocation rules, eligibility criteria
- **Relationships:** Campaign management for bulk wallet operations

### Setting (`app/Models/Setting.php`)
- **Purpose:** Application configuration registry powering CMS and storefront content
- **Key Fields:** `key`, `value` (JSON payload), `type`, `group`
- **Relationships:** Self-contained configuration system with media attachments via Mediable
- **Special Features:** `witImages()` helper resolves stored media IDs into `MediaData` DTOs; integrates with SettingsService and SmartCache invalidation to serve hydrated settings payloads

### HomePageBlock (`app/Models/HomePageBlock.php`)
- **Purpose:** Dynamic homepage block definitions rendered on the shop front
- **Key Fields:** `type` (enum), `title`, `location`, `content` (JSON), `order`, `is_active`
- **Relationships:** Media attachments for block imagery via Mediable
- **Special Features:** Enum-backed block type with boolean activation flag and ordering casts for precise layout control

### Slider (`app/Models/Slider.php`)
- **Purpose:** Homepage and promotional slider management with publication workflow
- **Key Fields:** `title`, `caption`, `image_id`, `image_url`, `image_alt`, `status`, `link`, `order`
- **Relationships:** Media attachments for slider images, `getImage()` helper returns hydrated `MediaData`
- **Special Features:** `active()` scope filters to published sliders via `PublicationStatusEnum`; leverages Mediable for tagged image retrieval

### Partner (`app/Models/Partner.php`)
- **Purpose:** Strategic partner logos and showcases for marketing sections
- **Key Fields:** `title`, `caption`, `image_id`, `image_url`, `image_alt`, `url`, `show_in`, `order`, `is_active`
- **Relationships:** Media attachments for partner imagery with `getImage()` helper returning `MediaData`
- **Special Features:** `active()` scope limits to visible partners; `show_in` enum targets specific storefront regions

### StudentStory (`app/Models/StudentStory.php`)
- **Purpose:** Success stories and testimonials showcased on the storefront
- **Key Fields:** `student_name`, `course_name`, `course_url`, `story_text`, `avatar_url`, `is_visible`, `is_featured`, `display_order`
- **Relationships:**
  - Media attachments (avatar) via Mediable; `avatar_url` now persisted rather than computed on the fly
  - `belongsToMany(Category::class)` via `HasCategories`
  - `belongsToMany(Course::class, 'course_student_story')` - courses featured in the testimonial
- **Special Features:** `visible()` and new `featured()` scopes drive storefront filtering; ordered display via `display_order`; category/course pivots enable admin/shop filtering; seeded demo data ships via `database/demo/student_stories.json`.

### course_student_story (Pivot)
- **Purpose:** Associates curated StudentStory testimonials with one or more Courses for filtering in admin/shop surfaces
- **Key Fields:** `course_id`, `student_story_id`
- **Constraints:** Composite primary key with cascading deletes to keep links in sync when either side is removed
- **Usage:** Powers admin filters (`filter[course_id]`) and shop queries (`course_slug`) when retrieving relevant testimonials.

### CollaborationRequest (`app/Models/CollaborationRequest.php`)
- **Purpose:** Incoming collaboration and partnership requests submitted from the shop
- **Key Fields:** `full_name`, `phone`, `email`, `department`, `message`
- **Relationships:** Media attachments for supporting documents via Mediable
- **Special Features:** Factory-backed model used by public collaboration form endpoints

### AdviceRequest (`app/Models/AdviceRequest.php`)
- **Purpose:** Callback requests for educational counseling follow-up
- **Key Fields:** `phone`, `status`, `note`, `handled_by_id`
- **Relationships:** `belongsTo(Staff::class, 'handled_by_id')` - handler
- **Special Features:** Enum-backed `status` with timestamps for handling workflow

---

## Recent Model Behavior Notes

### ProductDeliveryOption Capacity
- Capacity is enforced at checkout time; `enrolled_count` is used against `capacity` when creating orders.

### DiscountPromotion & DiscountCoupon Counters
- `DiscountPromotion.total_usage_count` and `DiscountCoupon.usage_count` increment only on successful checkout when the promotion/coupon is applied.

### Payment Verification State
- Payment verification is idempotent; only `PENDING` payments transition to `COMPLETED`. Re-verification attempts on non-pending payments are rejected.

### Order & OrderItem Discount Snapshots
- `Order.applied_cart_discounts_json` captures cart-level discounts at checkout.
- `OrderItem.applied_discount_details_json` captures item-level discount details at checkout.

### ContactUsRequest (`app/Models/ContactUsRequest.php`)
- **Purpose:** Customer contact form submissions captured from the shop CMS
- **Key Fields:** `full_name`, `phone`, `subject`, `email`, `message`
- **Relationships:** Self-contained request records for support follow-up

### SmsLog (`app/Models/SmsLog.php`)
- **Purpose:** SMS delivery tracking and logging
- **Key Fields:** `provider`, `status`, `to` (array of recipients), `message`, `data`, `sent_at`
- **Relationships:** Self-contained audit records for outbound SMS
- **Special Features:** Casts payload and recipient metadata to arrays for structured logging

### BlogCategory (`app/Models/Blog/BlogCategory.php`)
- **Purpose:** Hierarchical blog content organization
- **Key Fields:** `name`, `slug`, `description`, `parent_id`, `icon`, `meta_title`, `meta_description`, `meta_keywords`
- **Relationships:**
  - `belongsTo(self::class, 'parent_id')` - parent (self-referencing hierarchy)
  - `hasMany(self::class, 'parent_id')` - children (self-referencing hierarchy)
  - `belongsToMany(BlogPost::class, 'blog_post_category')` - posts
- **Special Features:** Media attachments for icons, SEO metadata fields for shop detail payloads, hierarchical structure similar to Category model, and published post counts sourced from the pivot.

### BlogPost (`app/Models/Blog/BlogPost.php`)
- **Purpose:** Blog content management with publication workflow and content relationships
- **Key Fields:** `title`, `slug`, `body`, `excerpt`, `author_id`, `status`, `published_at`, `read_time_minutes`, `is_featured`, `thumbnail_url`, `main_productable_id`, `main_productable_type`, `meta_title`, `meta_description`, `meta_keywords`
- **Relationships:**
  - `belongsTo(Staff::class, 'author_id')` - author
  - `belongsToMany(BlogCategory::class, 'blog_post_category')` - categories
  - `morphToMany(Course::class, 'productable', 'blog_post_productables')` - courses
  - `morphToMany(Seminar::class, 'productable', 'blog_post_productables')` - seminars
  - `morphToMany(DigitalAsset::class, 'productable', 'blog_post_productables')` - digitalAssets
  - `morphTo()` - mainProductable (single featured productable)
  - `morphMany(Review::class, 'reviewable')` - reviews
- **Traits:** Uses `HasMedia`, `HasReview`, and `Searchable` traits for standardized media management, review aggregation, and Scout/Typesense indexing
- **Special Features:** Publication workflow with DRAFT/PUBLISHED/SCHEDULED/ARCHIVED statuses, automated read time calculation, featured content system, polymorphic relationships to educational content, automatic cover image URL generation from media, and enriched storefront payloads supplying author data, active categories, SEO metadata, and media collections.
