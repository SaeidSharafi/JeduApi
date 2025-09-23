# Complete Application Database Schema

## User & Staff Management

### Table: `users`
- **Purpose:** Stores customer account information. This is the primary table for end-users of the e-commerce platform.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `uuid` (UUID NOT NULL UNIQUE): A public, unique identifier for the user.
    - `first_name` (VARCHAR(100)): User's first name.
    - `last_name` (VARCHAR(100)): User's last name.
    - `phone` (VARCHAR(20) NOT NULL UNIQUE): Primary mobile number, used for login and notifications.
    - `phone2` (VARCHAR(20)): Secondary contact number.
    - `phone_verified_at` (TIMESTAMP): Timestamp for when the primary phone was verified.
    - `email` (VARCHAR(255) UNIQUE): User's email address.
    - `email_verified_at` (TIMESTAMP): Timestamp for when the email was verified.
    - `password` (VARCHAR(255)): Hashed password for password-based login.
    - `civil_id` (VARCHAR(255)): National or civil identification number.
    - `civil_id_type` (VARCHAR(255)): Type of civil ID (e.g., 'national_id').
    - `date_of_birth` (DATE): User's date of birth.
    - `father_name` (VARCHAR(100)): User's father's name.
    - `gender` (VARCHAR(255)): User's gender.
    - `education_level` (VARCHAR(20)): Highest level of education achieved.
    - `field_of_study` (VARCHAR(255)): Academic field of study.
    - `education_status` (VARCHAR(20)): Current education status.
- **Relationships & Foreign Keys:** None. This is a primary entity.
- **Indexes:**
    - `PRIMARY KEY`: `id`
    - `UNIQUE`: `uuid`, `phone`, `email`, (`civil_id_type`, `civil_id`)
    - `INDEX`: (`last_name`, `first_name`), `civil_id`, `date_of_birth` for search performance.

### Table: `staff`
- **Purpose:** Stores accounts for administrative users who manage the platform.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `name` (VARCHAR(255)): Staff member's name.
    - `email` (VARCHAR(255) UNIQUE): Staff member's email address.
    - `phone` (VARCHAR(20) NOT NULL UNIQUE): Staff member's phone number, used for login.
    - `password` (VARCHAR(255)): Hashed password.
    - `is_admin` (BOOLEAN NOT NULL DEFAULT false): Super-admin privilege flag.
    - `remember_token` (VARCHAR(100)): "Remember me" token.
- **Relationships & Foreign Keys:** None. This is a primary entity for the admin guard.

### Table: `teachers`
- **Purpose:** Stores profiles and detailed information for instructors.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `first_name`, `last_name` (VARCHAR(255) NOT NULL): Teacher's name.
    - `bio` (TEXT NOT NULL): Detailed biography.
    - `rate` (DOUBLE PRECISION): Teacher's rating.
    - `email` (VARCHAR(255) NOT NULL UNIQUE): Teacher's contact email.
    - `phone` (VARCHAR(255)): Teacher's contact phone.
    - `gender` (VARCHAR(255)): Teacher's gender.
    - `birth_date` (DATE): Teacher's birth date.
    - `social_links` (JSON): JSON object for social media links.
    - `user_id` (BIGINT NOT NULL): The associated user account.
    - `created_by` (BIGINT): The staff member who created this record.
- **Relationships & Foreign Keys:**
    - `FK(user_id)` -> `users(id)` ON DELETE RESTRICT. A user record cannot be deleted if it is linked to a teacher.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.
- **Indexes:**
    - `PRIMARY KEY`: `id`
    - `UNIQUE`: `email`

---
## Product & Catalog Management

### Table: `vendors`
- **Purpose:** Represents internal departments or external entities that offer products.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `name` (VARCHAR(255) NOT NULL): The name of the vendor/department.
    - `email`, `phone`, `phone2`: Contact information.
    - `address`, `map_location`: Physical location details.
    - `logo_url`, `favicon_url`: URLs for branding images.
    - `social_links`, `theme_options` (JSON): Flexible fields for additional data.
- **Relationships & Foreign Keys:** None.

### Table: `terms`
- **Purpose:** Defines academic terms or scheduling periods (e.g., "Fall 2025").
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `name` (VARCHAR(255) NOT NULL): The name of the term.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'inactive'): The current status (e.g., 'active', 'inactive').
    - `academic_year` (VARCHAR(255)): The academic year this term belongs to.
    - `start_date`, `end_date` (DATE): The start and end dates of the term.
- **Relationships & Foreign Keys:** None.
- **Indexes:** `status`.

### Table: `categories`
- **Purpose:** Provides hierarchical organization for products.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `parent_id` (BIGINT): Self-referencing key for creating a parent-child hierarchy.
    - `name` (VARCHAR(255) NOT NULL UNIQUE): The category name.
    - `slug` (VARCHAR(255) NOT NULL UNIQUE): URL-friendly identifier.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'published'): Publication status.
    - `description`, `image_url`, `icon_url`, `educational_calendar_url`: Descriptive and media fields.
    - `created_by` (BIGINT): Staff member who created the category.
- **Relationships & Foreign Keys:**
    - `FK(parent_id)` -> `categories(id)` ON DELETE RESTRICT. A parent category cannot be deleted if it has children.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.
- **Indexes:** `PRIMARY KEY`, `UNIQUE(name)`, `UNIQUE(slug)`, `status`.

### Table: `courses`, `seminars`, `digital_assets`
- **Purpose:** These tables store the unique attributes for the three different types of products. They are the "productable" entities.
- **Common Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `slug` (VARCHAR(255) UNIQUE): URL-friendly identifier.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'draft'): Publication status.
    - `full_name` / `name`, `description`: Core descriptive fields.
    - `meta_title`, `meta_description`, `meta_keywords`: SEO fields.
    - `created_by` (BIGINT): The staff member who created the record.
    - `review_count` (INT) and `average_rating` (DECIMAL(3,2)): Denormalized review data for quick access.
- ** `courses` Specific Columns:**
    - `short_name`, `thumbnail_url`, `sample_certificate_image_url`, `duration` (INT), `difficulty_level`, `career_prospects_text`, `curriculum_summary_text`, `outcomes_json` (JSON), `default_teacher_info`, `additional_info` (JSON).
- ** `seminars` Specific Columns:**
    - `subtitle`, `thumbnail_url`, `learning_objectives`, `prerequisites`, `promo_video_external_url`, `estimated_duration_desc`.
- ** `digital_assets` Specific Columns:**
    - `thumbnail_url`, `version` (VARCHAR(50)), `page_count` (INT), `duration_seconds` (INT), `is_attachable_to_course` (BOOLEAN).
- **Relationships & Foreign Keys:**
    - Each of these tables has a polymorphic `morphOne` relationship to the `products` table.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.

### Table: `products`
- **Purpose:** The central, sellable entity that links a product type (Course, Seminar, etc.) with common e-commerce attributes like vendor and term.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `vendor_id` (BIGINT NOT NULL): Foreign key to `vendors`.
    - `productable_id` (BIGINT NOT NULL): The ID of the related model (e.g., an ID from `courses`).
    - `productable_type` (VARCHAR(255) NOT NULL): The class name of the related model.
    - `term_id` (BIGINT NOT NULL): Foreign key to `terms`.
    - `status`, `is_visible`, `is_featured`: Control visibility and promotion.
    - `name`, `short_name`, `slug`: Display names and identifiers.
    - `details_json` (JSONB NOT NULL): Flexible storage for additional product attributes.
- **Relationships & Foreign Keys:**
    - `FK(vendor_id)` -> `vendors(id)` ON DELETE NO ACTION.
    - `FK(term_id)` -> `terms(id)` ON DELETE NO ACTION.
    - `morphTo('productable')`: Connects to a `courses`, `seminars`, or `digital_assets` record.
- **Indexes:** (`productable_type`, `productable_id`) for the polymorphic relation.

### Table: `product_delivery_options`
- **Purpose:** Defines a specific way a product can be purchased, including its unique price, schedule, and delivery method. A single product can have many delivery options.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `product_id` (BIGINT NOT NULL): Foreign key to `products`.
    - `sku` (VARCHAR(255) NOT NULL UNIQUE): Unique stock-keeping unit.
    - `price` (BIGINT NOT NULL): The standard price.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'draft'): Availability status.
    - `is_featured`, `featured_price`, `featured_price_start_date`, `featured_price_end_date`: Fields for promotional pricing.
    - `registration_start_date`, `registration_end_date`: The window for customer registration.
- **Relationships & Foreign Keys:**
    - `FK(product_id)` -> `products(id)` ON DELETE CASCADE. Deleting a product deletes its delivery options.
- **Indexes:** `UNIQUE(sku)`, `status`.

---
## Order & Payment Management

### Table: `orders`
- **Purpose:** The central table for customer transactions, holding a summary of each purchase.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `increment_id` (VARCHAR(255) NOT NULL UNIQUE): A human-readable, unique order identifier.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'pending'): The current status (e.g., pending, completed).
    - `customer_id` (BIGINT NOT NULL): Foreign key to `users`.
    - `customer_snapshot_json` (JSONB NOT NULL): A complete JSON snapshot of the customer's data at the time of order creation for historical accuracy.
    - `grand_total` (BIGINT NOT NULL): The final amount paid.
    - `applied_coupon_code` (VARCHAR(255)): The coupon code used for the order.
    - `created_by` (BIGINT): Staff member who may have created the order manually.
- **Relationships & Foreign Keys:**
    - `FK(customer_id)` -> `users(id)` ON DELETE NO ACTION.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.
- **Indexes:** `UNIQUE(increment_id)`, `status`, (`customer_id`, `status`).

### Table: `order_items`
- **Purpose:** Represents a single line item within an order.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `order_id` (BIGINT NOT NULL): Foreign key to `orders`.
    - `product_delivery_option_id` (BIGINT): Foreign key to the specific `product_delivery_options` purchased.
    - `vendor_id` (BIGINT): Denormalized vendor ID for reporting.
    - `product_data_snapshot_json` (JSONB NOT NULL): A snapshot of the product and delivery option data at the time of purchase.
    - `price`, `total`, `discount_amount`: Financial details for this line item.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'completed'): Status of this specific item.
- **Relationships & Foreign Keys:**
    - `FK(order_id)` -> `orders(id)` ON DELETE CASCADE.
    - `FK(product_delivery_option_id)` -> `product_delivery_options(id)` ON DELETE SET NULL.
    - `FK(vendor_id)` -> `vendors(id)` ON DELETE SET NULL.
- **Indexes:** `status`, (`status`, `created_at`).

### Table: `payments`
- **Purpose:** Records financial transactions (payments received) associated with an order.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `order_id` (BIGINT NOT NULL): Foreign key to `orders`.
    - `customer_id` (BIGINT NOT NULL): Foreign key to `users`.
    - `amount` (BIGINT NOT NULL): The amount paid.
    - `method` (VARCHAR(255) NOT NULL): Payment method used (e.g., 'wallet', 'bank_transfer').
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'pending'): Status of the payment (e.g., pending, completed, failed).
    - `data` (JSONB): Stores gateway responses or other payment details.
    - `created_by` (BIGINT): Staff member who recorded the payment.
- **Relationships & Foreign Keys:**
    - `FK(order_id)` -> `orders(id)` ON DELETE CASCADE.
    - `FK(customer_id)` -> `users(id)` ON DELETE RESTRICT.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.

### Table: `refunds`
- **Purpose:** Manages refund transactions for specific order items.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `order_id`, `order_item_id`, `customer_id`: Foreign keys for context.
    - `amount`, `deduction_amount` (BIGINT NOT NULL): Refund amounts.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'pending'): Status of the refund request.
    - `transaction_details` (JSON NOT NULL): Details of the refund transaction.
    - `created_by` (BIGINT): Staff member who processed the refund.
- **Relationships & Foreign Keys:**
    - `FK(order_id)` -> `orders(id)` ON DELETE CASCADE.
    - `FK(order_item_id)` -> `order_items(id)` ON DELETE CASCADE.
    - `FK(customer_id)` -> `users(id)` ON DELETE SET NULL.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.

### Table: `enrolments`
- **Purpose:** Connects a customer to a purchased product, granting them access. This is the bridge between a transaction and content access.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `uuid` (UUID NOT NULL UNIQUE): A public, unique identifier for the enrolment.
    - `order_id`, `order_item_id`, `customer_id`, `product_delivery_option_id`: Foreign keys linking all relevant entities.
    - `enrollment_status` (VARCHAR(255) NOT NULL): Status of access (e.g., 'active', 'pending_provisioning', 'expired').
    - `access_start_date`, `access_end_date` (DATE): The validity period of the access.
- **Relationships & Foreign Keys:**
    - `FK(order_id)` -> `orders(id)` ON DELETE CASCADE.
    - `FK(order_item_id)` -> `order_items(id)` ON DELETE CASCADE.
    - `FK(customer_id)` -> `users(id)` ON DELETE CASCADE.
    - `FK(product_delivery_option_id)` -> `product_delivery_options(id)` ON DELETE CASCADE.

---
## Discount & Wallet System

### Table: `discount_promotions`
- **Purpose:** The master table for a discount, promotion, or coupon campaign.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `name` (VARCHAR(255) NOT NULL): The name of the promotion.
    - `type` (VARCHAR(255) NOT NULL): The type of promotion (e.g., 'cart_rule', 'coupon').
    - `is_active`, `starts_at`, `ends_at`, `priority`: Control the application logic of the discount.
- **Relationships & Foreign Keys:** None.

### Table: `discount_promotion_rules`
- **Purpose:** Stores the specific conditions and actions for a `discount_promotion`. A promotion can have many rules.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `discount_promotion_id` (BIGINT NOT NULL): Foreign key to `discount_promotions`.
    - `type` (VARCHAR(255) NOT NULL): The type of rule (e.g., 'condition', 'action').
    - `handler` (VARCHAR(255) NOT NULL): The class responsible for processing this rule.
    - `configuration` (JSONB NOT NULL): JSON object containing the specific parameters for the rule handler.
- **Relationships & Foreign Keys:**
    - `FK(discount_promotion_id)` -> `discount_promotions(id)` ON DELETE CASCADE.

### Table: `discount_coupons`
- **Purpose:** Stores individual coupon codes linked to a promotion.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `discount_promotion_id` (BIGINT NOT NULL): Foreign key to `discount_promotions`.
    - `code` (VARCHAR(255) NOT NULL UNIQUE): The actual coupon code customers enter.
    - `usage_limit`, `usage_count`: Tracks the usage of the coupon.
- **Relationships & Foreign Keys:**
    - `FK(discount_promotion_id)` -> `discount_promotions(id)` ON DELETE CASCADE.

### Table: `wallets`
- **Purpose:** Manages a user's credit and gift balances.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `user_id` (BIGINT NOT NULL UNIQUE): Foreign key to `users`.
    - `balance`, `gift_balance` (BIGINT NOT NULL DEFAULT 0): The user's credit balances.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'active'): Status of the wallet.
    - `created_by` (BIGINT): Staff member who created the wallet.
- **Relationships & Foreign Keys:**
    - `FK(user_id)` -> `users(id)` ON DELETE CASCADE.
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.

### Table: `wallet_transactions`
- **Purpose:** An immutable log of every credit or debit operation on a user's wallet.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `wallet_id`, `user_id`: Foreign keys to `wallets` and `users`.
    - `type` (VARCHAR(255) NOT NULL): The transaction type (e.g., 'deposit', 'withdrawal', 'refund').
    - `amount` (BIGINT NOT NULL): The transaction amount (can be negative).
    - `balance_after`, `gift_balance_after`: The balances after this transaction occurred.
    - `source_type`, `source_id`: A polymorphic relationship to the source of the transaction (e.g., an Order, a Refund, a WalletCampaign).
- **Relationships & Foreign Keys:**
    - `FK(wallet_id)` -> `wallets(id)` ON DELETE CASCADE.
    - `FK(user_id)` -> `users(id)` ON DELETE RESTRICT.

### Table: `wallet_campaigns`
- **Purpose:** Manages campaigns for bulk distribution of wallet credits.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `name` (VARCHAR(255) NOT NULL): The campaign name.
    - `amount` (BIGINT NOT NULL): The amount of credit to be allocated per user.
    - `is_active`, `starts_at`, `ends_at`: Control the campaign's lifecycle.
    - `created_by` (BIGINT): Staff member who created the campaign.
- **Relationships & Foreign Keys:**
    - `FK(created_by)` -> `staff(id)` ON DELETE SET NULL.

---
## Content & Settings Management

### Table: `blog_categories` & `blog_posts`
- **Purpose:** A standard blogging system. `blog_categories` are hierarchical, and `blog_posts` contain the articles.
- **`blog_posts` Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `title`, `slug` (UNIQUE), `body`, `excerpt`: Standard blog fields.
    - `author_id` (BIGINT): The `staff` member who wrote the post.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'draft'): Publication workflow status.
    - `published_at` (TIMESTAMP): The scheduled publication time.
    - `main_productable_type`, `main_productable_id`: A polymorphic link to a featured product.
- **Relationships & Foreign Keys:**
    - `blog_categories` has a self-referencing `parent_id` foreign key.
    - `blog_posts.author_id` -> `staff(id)` ON DELETE SET NULL.
    - A many-to-many relationship exists between `blog_posts` and `blog_categories` via the `blog_post_category` pivot table.

### Table: `settings`
- **Purpose:** A key-value store for managing global application settings.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `key` (VARCHAR(255) NOT NULL UNIQUE): The unique key for the setting (e.g., 'contact_info').
    - `value` (JSON NOT NULL): The setting value, stored as a flexible JSON object.
    - `group` (VARCHAR(255)): A group name for organization (e.g., 'homepage', 'footer').
- **Relationships & Foreign Keys:** None.

### Table: `home_page_blocks`, `sliders`, `student_stories`
- **Purpose:** These tables store content for various components of the website's front end.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `title`, `caption`, `image_url`, `link`, `order`, `is_active` / `is_visible`: Common fields for controlling the display of content blocks, sliders, and stories.
- **Relationships & Foreign Keys:** None.

---
## System & Auditing

### Table: `admin_action_logs`
- **Purpose:** A detailed audit trail for all actions performed by staff members, crucial for security and compliance.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `admin_id` (BIGINT NOT NULL): The `staff` member who performed the action.
    - `action_type` (VARCHAR(50) NOT NULL): The type of action (e.g., 'create', 'update', 'delete').
    - `resource_type`, `resource_id`: A polymorphic link to the model that was affected.
    - `route_name`, `http_method`, `request_data` (JSONB): Full details of the API request.
    - `risk_level` (VARCHAR(255) NOT NULL DEFAULT 'low'): An assessed risk level for the action.
- **Relationships & Foreign Keys:**
    - `FK(admin_id)` -> `staff(id)` ON DELETE RESTRICT.

### Table: `reviews`
- **Purpose:** Stores customer reviews for various entities like products and courses.
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `user_id` (BIGINT NOT NULL): The user who wrote the review.
    - `reviewable_type`, `reviewable_id`: A polymorphic relationship to the item being reviewed.
    - `rating` (SMALLINT): The star rating.
    - `comment` (TEXT NOT NULL): The review text.
    - `status` (VARCHAR(255) NOT NULL DEFAULT 'pending'): Moderation status (pending, approved, rejected).
    - `is_featured` (BOOLEAN NOT NULL): Flag to feature the review.
- **Relationships & Foreign Keys:**
    - `FK(user_id)` -> `users(id)` ON DELETE CASCADE.
- **Indexes:** (`reviewable_type`, `reviewable_id`), (`user_id`, `reviewable_type`, `reviewable_id`) for uniqueness.

### Table: `sms_logs`
- **Purpose:** Logs all outgoing SMS messages sent by the system (e.g., OTPs).
- **Columns:**
    - `id` (SERIAL): **Primary Key**.
    - `status` (INTEGER NOT NULL): The status code from the SMS provider.
    - `data` (JSONB): The full response from the provider.
    - `content` (TEXT): The message body.
    - `to` (JSONB): The recipient phone number(s).
- **Relationships & Foreign Keys:** None.

---
## Pivot & Relationship Tables

- **`assetables`**: Polymorphic pivot table linking `digital_assets` to other models (like `courses`).
- **`categorizables`**: Polymorphic pivot table linking `categories` to products.
- **`blog_post_category`**: Many-to-many pivot between `blog_posts` and `blog_categories`.
- **`blog_post_productables`**: Polymorphic pivot linking `blog_posts` to various product types.
- **`mediables`**: Polymorphic pivot table from the `plank/laravel-mediable` package, linking `media` records to any model.
- **`product_delivery_option_teacher`**: Many-to-many pivot between `product_delivery_options` and `teachers`.
- **Spatie Permission Tables (`roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`)**: Standard tables for managing role-based access control for the `staff` model.
