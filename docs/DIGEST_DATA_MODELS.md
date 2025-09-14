# Digest: Data Models & Relationships

### User (`app/Models/User.php`)
- **Purpose:** Customer accounts for the e-commerce platform
- **Key Fields:** `uuid`, `first_name`, `last_name`, `email`, `phone`, `password`, `civil_id`, `date_of_birth`, `gender`, `education_level`
- **Relationships:** 
  - `hasOne(Teacher::class)` - teacherData
  - `hasMany(Enrolment::class, 'customer_id')` - enrolments
  - `hasOne(Wallet::class)` - wallet
- **Guard:** `user` (Sanctum authentication)

### Staff (`app/Models/Staff.php`)
- **Purpose:** Admin users with role-based permissions
- **Key Fields:** `name`, `email`, `password`, permission-related fields
- **Relationships:** Uses Spatie Permission package for roles/permissions
- **Guard:** `staff` (Sanctum authentication)

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
- **Key Fields:** `vendor_id`, `productable_id`, `productable_type`, `term_id`, `status`, `name`, `short_description`, `is_featured`, `details_json`
- **Relationships:**
  - `morphTo()` - productable (Course, Seminar, DigitalAsset)
  - `belongsTo(Vendor::class)` - vendor
  - `belongsTo(Term::class)` - term
  - `hasMany(ProductDeliveryOption::class)` - productDeliveryOptions
  - Uses `HasCategories` trait for categorization

### Course (`app/Models/Course.php`)
- **Purpose:** Educational course definitions and blueprints
- **Key Fields:** Course-specific metadata and content structure
- **Relationships:** Polymorphic relationship as `productable` to Product

### Seminar (`app/Models/Seminar.php`)
- **Purpose:** One-off educational events
- **Key Fields:** Seminar-specific scheduling and content metadata
- **Relationships:** Polymorphic relationship as `productable` to Product

### DigitalAsset (`app/Models/DigitalAsset.php`)
- **Purpose:** Standalone digital products (PDFs, videos, etc.)
- **Key Fields:** Digital asset metadata and file associations
- **Relationships:** Polymorphic relationship as `productable` to Product

### ProductDeliveryOption (`app/Models/ProductDeliveryOption.php`)
- **Purpose:** Specific purchase/delivery methods per product with pricing
- **Key Fields:** Pricing, delivery terms, availability
- **Relationships:**
  - `belongsTo(Product::class)` - product
  - Purchase options for products

### OrderItem (`app/Models/OrderItem.php`)
- **Purpose:** Individual line items within orders
- **Key Fields:** `order_id`, `product_delivery_option_id`, `qty_ordered`, `status`, pricing fields
- **Relationships:**
  - `belongsTo(Order::class)` - order
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `hasOne(Enrolment::class)` - enrolment

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
- **Relationships:** `belongsTo(Order::class)` - order

### Refund (`app/Models/Refund.php`)
- **Purpose:** Refund transaction records
- **Key Fields:** Refund amounts, status, reasoning
- **Relationships:** Links to OrderItem and Payment records

### Category (`app/Models/Category.php`)
- **Purpose:** Hierarchical product organization
- **Key Fields:** `name`, `parent_id`, hierarchical structure
- **Relationships:** Self-referencing hierarchy, many-to-many with categorizable models

### Teacher (`app/Models/Teacher.php`)
- **Purpose:** Instructor profiles
- **Key Fields:** Instructor metadata and qualifications
- **Relationships:** `belongsTo(User::class)` - user

### Vendor (`app/Models/Vendor.php`)
- **Purpose:** Internal departments/external entities
- **Key Fields:** Vendor information and business details
- **Relationships:** `hasMany(Product::class)` - products

### DiscountPromotion (`app/Models/DiscountPromotion.php`)
- **Purpose:** Advanced discount/coupon system
- **Key Fields:** Discount rules, conditions, and actions
- **Relationships:** Complex rule-based discount system with conditions and actions

### Wallet (`app/Models/Wallet.php`)
- **Purpose:** User wallet system for credits and transactions
- **Key Fields:** `user_id`, `balance`, wallet metadata
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `hasMany(WalletTransaction::class)` - transactions

### WalletTransaction (`app/Models/WalletTransaction.php`)
- **Purpose:** Individual wallet transaction records
- **Key Fields:** Transaction amounts, types, source tracking
- **Relationships:** `belongsTo(Wallet::class)` - wallet