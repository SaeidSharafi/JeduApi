# Digest: Data Models & Relationships

### User (`app/Models/User.php`)
- **Purpose:** Customer accounts for the e-commerce platform
- **Key Fields:** `uuid`, `first_name`, `last_name`, `email`, `phone`, `password`, `civil_id`, `date_of_birth`, `gender`, `education_level`
- **Relationships:** 
  - `hasOne(Teacher::class)` - teacherData
  - `hasMany(Enrolment::class, 'customer_id')` - enrolments
  - `hasOne(Wallet::class)` - wallet
  - `hasMany(Review::class)` - reviews
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
  - `hasMany(Enrolment::class, 'order_id')` - enrolments
  - `belongsTo(User::class, 'customer_id')` - customer
- **Special Features:** Auto-incrementing order numbers, payment status calculations

### Product (`app/Models/Product.php`)
- **Purpose:** Sellable instances of educational content with polymorphic relationships
- **Key Fields:** `vendor_id`, `productable_id`, `productable_type`, `term_id`, `status`, `name`, `short_description`, `slug`, `is_featured`, `details_json`
- **Relationships:**
  - `morphTo()` - productable (Course, Seminar, DigitalAsset)
  - `belongsTo(Vendor::class)` - vendor
  - `belongsTo(Term::class)` - term
  - `hasMany(ProductDeliveryOption::class)` - productDeliveryOptions
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - Uses `HasCategories` trait for categorization
- **Special Features:** Active product scopes with relationship filtering, automatic slug management from productable entities

### Course (`app/Models/Course.php`)
- **Purpose:** Educational course definitions and blueprints
- **Key Fields:** Course-specific metadata and content structure
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - `morphToMany(DigitalAsset::class, 'assetable')` - digitalAssets
  - `morphToMany(BlogPost::class, 'productable', 'blog_post_productables')` - blogPosts
- **Traits:** Uses `HasMedia` trait for standardized media management with tagged media support

### Seminar (`app/Models/Seminar.php`)
- **Purpose:** One-off educational events
- **Key Fields:** Seminar-specific scheduling and content metadata
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - `morphToMany(BlogPost::class, 'productable', 'blog_post_productables')` - blogPosts
- **Traits:** Uses `HasMedia` trait for standardized media management with tagged media support

### DigitalAsset (`app/Models/DigitalAsset.php`)
- **Purpose:** Standalone digital products (PDFs, videos, etc.)
- **Key Fields:** Digital asset metadata and file associations
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
- **Traits:** Uses `HasMedia` trait for standardized media management with tagged media support

### ProductDeliveryOption (`app/Models/ProductDeliveryOption.php`)
- **Purpose:** Specific purchase/delivery methods per product with pricing
- **Key Fields:** Pricing, delivery terms, availability
- **Relationships:**
  - `belongsTo(Product::class)` - product
  - `hasMany(ProductDeliveryOptionDiscountPrice::class)` - discountPrices
  - Purchase options for products

### ProductDeliveryOptionDiscountPrice (`app/Models/ProductDeliveryOptionDiscountPrice.php`)
- **Purpose:** Discount pricing records for specific delivery options
- **Key Fields:** `product_delivery_option_id`, `discount_promotion_id`, `discounted_price`, discount metadata
- **Relationships:**
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `belongsTo(DiscountPromotion::class)` - discountPromotion
- **Traits:** Uses `HasFactory` trait for test data generation

### OrderItem (`app/Models/OrderItem.php`)
- **Purpose:** Individual line items within orders
- **Key Fields:** `order_id`, `product_delivery_option_id`, `qty_ordered`, `status`, pricing fields
- **Relationships:**
  - `belongsTo(Order::class)` - order
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `hasOne(Enrolment::class)` - enrolment
  - `hasMany(Refund::class)` - refunds

### Enrolment (`app/Models/Enrolment.php`)
- **Purpose:** Student access records linking users to purchased products
- **Key Fields:** `customer_id`, `order_id`, `order_item_id`, `enrollment_status`, access control fields
- **Relationships:**
  - `belongsTo(User::class, 'customer_id')` - customer
  - `belongsTo(Order::class)` - order
  - `belongsTo(OrderItem::class)` - orderItem

### Payment (`app/Models/Payment.php`)
- **Purpose:** Financial transaction handling
- **Key Fields:** `order_id`, `amount`, `status`, payment gateway details
- **Relationships:** 
  - `belongsTo(Order::class)` - order
  - `hasMany(Refund::class)` - refunds

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

### Teacher (`app/Models/Teacher.php`)
- **Purpose:** Instructor profiles
- **Key Fields:** Instructor metadata and qualifications
- **Relationships:** `belongsTo(User::class)` - user

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

### WalletCampaign (`app/Models/WalletCampaign.php`)
- **Purpose:** Bulk wallet credit campaigns and promotions
- **Key Fields:** Campaign details, allocation rules, eligibility criteria
- **Relationships:** Campaign management for bulk wallet operations

### Setting (`app/Models/Setting.php`)
- **Purpose:** Application configuration and settings management
- **Key Fields:** `key`, `value`, `type`, `group`
- **Relationships:** Self-contained configuration system
- **Special Features:** Media integration, recursive image processing, type casting

### HomePageBlock (`app/Models/HomePageBlock.php`)
- **Purpose:** Dynamic content blocks for homepage layout
- **Key Fields:** Block content, positioning, display rules
- **Relationships:** Media attachments for images and content

### Slider (`app/Models/Slider.php`)
- **Purpose:** Homepage and promotional slider management
- **Key Fields:** Slider content, ordering, display settings
- **Relationships:** Media attachments for slider images

### StudentStory (`app/Models/StudentStory.php`)
- **Purpose:** Success stories and testimonials from students
- **Key Fields:** Story content, student information, featured status
- **Relationships:** Media attachments for photos and content

### CollaborationCarousel (`app/Models/CollaborationCarousel.php`)
- **Purpose:** Partner and collaboration showcase carousel
- **Key Fields:** Partner information, carousel ordering, display settings
- **Relationships:** Media attachments for partner logos

### CollaborationRequest (`app/Models/CollaborationRequest.php`)
- **Purpose:** Incoming collaboration and partnership requests
- **Key Fields:** Request details, contact information, status tracking
- **Relationships:** Contact form submissions for partnerships

### ContactUsRequest (`app/Models/ContactUsRequest.php`)
- **Purpose:** Customer contact form submissions
- **Key Fields:** Contact details, message content, response status
- **Relationships:** Customer service interaction tracking

### SmsLog (`app/Models/SmsLog.php`)
- **Purpose:** SMS delivery tracking and logging
- **Key Fields:** Phone numbers, message content, delivery status, provider details
- **Relationships:** SMS service audit trail

### BlogCategory (`app/Models/Blog/BlogCategory.php`)
- **Purpose:** Hierarchical blog content organization
- **Key Fields:** `name`, `slug`, `description`, `parent_id`, `icon`
- **Relationships:**
  - `belongsTo(self::class, 'parent_id')` - parent (self-referencing hierarchy)
  - `hasMany(self::class, 'parent_id')` - children (self-referencing hierarchy)
  - `belongsToMany(BlogPost::class, 'blog_post_category')` - posts
- **Special Features:** Media attachments for icons, hierarchical structure similar to Category model

### BlogPost (`app/Models/Blog/BlogPost.php`)
- **Purpose:** Blog content management with publication workflow and content relationships
- **Key Fields:** `title`, `slug`, `body`, `excerpt`, `author_id`, `status`, `published_at`, `read_time_minutes`, `is_featured`, `main_productable_id`, `main_productable_type`, `cover_image_url`
- **Relationships:**
  - `belongsTo(Staff::class, 'author_id')` - author
  - `belongsToMany(BlogCategory::class, 'blog_post_category')` - categories
  - `morphToMany(Course::class, 'productable', 'blog_post_productables')` - courses
  - `morphToMany(Seminar::class, 'productable', 'blog_post_productables')` - seminars
  - `morphToMany(DigitalAsset::class, 'productable', 'blog_post_productables')` - digitalAssets
  - `morphTo()` - mainProductable (single featured productable)
  - `morphMany(Review::class, 'reviewable')` - reviews
- **Traits:** Uses `HasMedia` trait for standardized media management with tagged media support
- **Special Features:** Publication workflow with DRAFT/PUBLISHED/SCHEDULED/ARCHIVED statuses, automated read time calculation, featured content system, polymorphic relationships to educational content, automatic cover image URL generation from media
