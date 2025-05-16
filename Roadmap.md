# Jedu E-Commerce: Admin Panel API Development Plan (Elaborated for Team)

**Version:** 1.0
**Date:** May 16, 2025
**Project Goal:** To build a robust and scalable backend API for the Jedu Admin Panel, enabling administrators to manage all aspects of the e-commerce platform for selling courses, seminars, and files.

**Base API Path for Admin Endpoints:** `/api/v1/admin/...`

**Authentication:** `Bearer token`

---

## Module 1: Core Educational Content Management

**Admin Goal:** To define and manage the fundamental educational offerings *before* they are made available for sale. This is the "master inventory" of intellectual property.

**Key Concepts:**
*   **Course Definition (`courses`):** The blueprint of a course (e.g., "Introduction to Photography"). This is not a specific class instance yet.
*   **Seminar Definition (`seminars`):** Similar to courses, but for one-off or distinct seminar events.
*   **Digital Files (`files`):** Reusable digital assets (PDFs, videos for non-SpotPlayer general use, documents) that can be sold standalone or attached to courses.
*   **Course-File Association (`course_files`):** How specific files (e.g., syllabus, lecture notes) are linked to a course definition, with context (`purpose`).

**Laravel Best Practices for this Module:**
*   Use Eloquent Models for `Course`, `Seminar`, `File`, with clearly defined relationships.
*   For file uploads within `File` management, use Laravel's Filesystem for abstraction (local, S3, etc.). Ensure secure handling and storage.

### 1.1. Course Management (`courses`, `course_files`)
    *   **Endpoints (Illustrative):**
        *   `GET /api/v1/admin/courses`: List courses (paginated, searchable by name/slug, filterable by status).
        *   `POST /api/v1/admin/courses`: Create a new course definition.
        *   `GET /api/v1/admin/courses/{course_id}`: View specific course details.
        *   `PUT /api/v1/admin/courses/{course_id}`: Update course details.
        *   `DELETE /api/v1/admin/courses/{course_id}`: Soft delete a course.
        *   `GET /api/v1/admin/courses/{course_id}/files`: List files associated with this course definition.
        *   `POST /api/v1/admin/courses/{course_id}/files`: Link an existing `file_id` (from `files` table) to the course, specifying `purpose` and `sort_order`.
        *   `PUT /api/v1/admin/courses/{course_id}/files/{file_id}`: Update the `purpose` or `sort_order` of a linked file.
        *   `DELETE /api/v1/admin/courses/{course_id}/files/{file_id}`: Unlink a file from the course.
    *   **Key Fields (`courses`):** `slug`, `full_name`, `short_name`, `description`, `image`, `video`, `certificate_sample`, `additional_info`, `default_teacher_info`, `meta_*`, `status`.
    *   **Key Fields (`course_files`):** `file_id`, `purpose`, `sort_order`.

### 1.2. Seminar Management (`seminars`)
    *   **Endpoints:** Similar CRUD structure to Courses.
        *   `GET /api/v1/admin/seminars`
        *   `POST /api/v1/admin/seminars`
        *   `GET /api/v1/admin/seminars/{seminar_id}`
        *   `PUT /api/v1/admin/seminars/{seminar_id}`
        *   `DELETE /api/v1/admin/seminars/{seminar_id}`
    *   **Key Fields:** `name`, `slug`, `description`, `image`, `video`, `default_teacher_info`, `extra_info`, `meta_*`, `status`.

### 1.3. File Management (`files`)
    *   **Endpoints:**
        *   `GET /api/v1/admin/files`: List all general files (paginated, searchable, filterable by `file_type`).
        *   `POST /api/v1/admin/files`: Upload a new file. Request body will be `multipart/form-data`. Response should include the `file_id` and URL.
        *   `GET /api/v1/admin/files/{file_id}`: View file metadata.
        *   `PUT /api/v1/admin/files/{file_id}`: Update file metadata (name, description, etc., not the file itself).
        *   `DELETE /api/v1/admin/files/{file_id}`: Soft delete file record (consider a cleanup job for actual stored files later).
    *   **Key Fields:** `name`, `slug`, `description`, `image` (preview), `preview_video`, `base_file_url`, `file_type`, `file_size_kb`, `extra_info`, `is_attachable_to_course`, `meta_*`, `status`.

---

## Module 2: Supporting Data Management

**Admin Goal:** To set up the organizational and descriptive structures needed to categorize, assign, and manage products effectively.

**Key Concepts:**
*   **Categories (`categories`):** Hierarchical system for organizing courses/products (e.g., هنر > عکاسی).
*   **Teachers (`teachers`):** Profiles of individuals who can be assigned to teach product offerings.
*   **Vendors (`vendors`):** Internal ACECR departments (e.g., "صنعت," "کودکان") responsible for offerings.

**Laravel Best Practices:**
*   For `categories` hierarchy, consider packages like `kalnoy/nestedset` if complex tree operations are frequent, or manage `parent_id` and `level` manually for simpler needs.

### 2.1. Category Management (`categories`)
    *   **Endpoints:**
        *   `GET /api/v1/admin/categories`: List categories. Consider query params for `parent_id` to fetch children or `?tree=true` for a nested structure.
        *   `POST /api/v1/admin/categories`
        *   `GET /api/v1/admin/categories/{category_id}`
        *   `PUT /api/v1/admin/categories/{category_id}`
        *   `DELETE /api/v1/admin/categories/{category_id}`
    *   **Key Fields:** `parent_id`, `name`, `slug`, `icon`, `banner`, `description`, `level`, `sort_order`, `is_featured`.

### 2.2. Teacher Management (`teachers`)
    *   **Endpoints:** Standard CRUD.
        *   `GET /api/v1/admin/teachers`
        *   `POST /api/v1/admin/teachers`
        *   `GET /api/v1/admin/teachers/{teacher_id}`
        *   `PUT /api/v1/admin/teachers/{teacher_id}`
        *   `DELETE /api/v1/admin/teachers/{teacher_id}`
    *   **Key Fields:** `name`, `title`, `bio`, `photo`, `contact_info`, `social_links`.

### 2.3. Vendor/Department Management (`vendors`)
    *   **Endpoints:** Standard CRUD.
        *   `GET /api/v1/admin/vendors`
        *   `POST /api/v1/admin/vendors`
        *   `GET /api/v1/admin/vendors/{vendor_id}`
        *   `PUT /api/v1/admin/vendors/{vendor_id}`
        *   `DELETE /api/v1/admin/vendors/{vendor_id}`
    *   **Key Fields:** `user_owner_id`, `name`, `slug`, `description`, `logo`, `banner`, `website`, `email`, `phone`, `address`, `tax_id`, `bank_account_details`, `status`.

---

## Module 3: Product & Offering Management

**Admin Goal:** To take the core educational content and supporting data, and create *specific, sellable instances*. This is where courses/seminars become tangible products with dates, prices, delivery methods, and assigned teachers.

**Key Concepts:**
*   **Product (`products`):** A sellable entity. It links an `item_type` (Course, Seminar, File) and `item_id` to a specific offering (e.g., "Introduction to Photography - Fall 2025 Semester"). It has dates and a general status.
*   **Product Delivery Option (`product_delivery_options`):** *Crucial Table*. For a single `Product`, defines the different ways it can be purchased/delivered (e.g., "In-Person Seat", "Online Access - Moodle", "Video Course License - SpotPlayer"). Each option has its own price, capacity, SKU, and *integration IDs* (`ims_course_id`, `rouyesh_course_id`).
*   **Prepayment Options (`prepayment_options`, `product_delivery_option_prepayments`):** Defines global installment amounts and links them to specific delivery options.

**Laravel Best Practices:**
*   The polymorphic relation in `products` (`item_type`, `item_id`) needs careful handling in Eloquent (`morphTo`).
*   Use Service classes or Action classes for complex logic.
*   Database transactions are vital for composite creation operations.

### 3.1. Product Management (`products`, `product_category`, `product_teachers`)
    *   **Endpoints:**
        *   `GET /api/v1/admin/products`
        *   `POST /api/v1/admin/products` (Requires `item_type`, `item_id`, `vendor_id`, `semester_or_term_name`, etc.)
        *   `GET /api/v1/admin/products/{product_id}`
        *   `PUT /api/v1/admin/products/{product_id}`
        *   `DELETE /api/v1/admin/products/{product_id}`
        *   `POST /api/v1/admin/products/{product_id}/categories`: Link categories (Body: `{ "category_id": ... }`).
        *   `DELETE /api/v1/admin/products/{product_id}/categories/{category_id}`: Unlink.
        *   `POST /api/v1/admin/products/{product_id}/teachers`: Assign teachers with a `role` (Body: `{ "teacher_id": ..., "role": "Lead Instructor" }`).
        *   `DELETE /api/v1/admin/products/{product_id}/teachers/{teacher_id}`: Unassign.
    *   **Key Fields (`products`):** `vendor_id`, `item_type`, `item_id`, `semester_or_term_name`, `start_date`, `end_date`, `status`, `is_visible`.

### 3.2. Product Delivery Option Management (`product_delivery_options`)
    *   **Endpoints (likely nested under products):**
        *   `GET /api/v1/admin/products/{product_id}/delivery-options`
        *   `POST /api/v1/admin/products/{product_id}/delivery-options`
        *   `GET /api/v1/admin/delivery-options/{option_id}` (or `/products/{product_id}/delivery-options/{option_id}`)
        *   `PUT /api/v1/admin/delivery-options/{option_id}`
        *   `DELETE /api/v1/admin/delivery-options/{option_id}`
    *   **Key Fields:** `product_id` (implicit), `sku`, `name` (user-facing option name), `delivery_method` (e.g., 'IN_PERSON', 'ONLINE_MOODLE', 'VIDEO_SPOTPLAYER'), `price`, `capacity`, `status`, `ims_course_id`, `rouyesh_course_id`, `access_details` (JSON for SpotPlayer keys, Moodle URLs etc.), `sort_order`.

### 3.3. Prepayment Option Management (`prepayment_options`, `product_delivery_option_prepayments`)
    *   **Endpoints:**
        *   `GET /api/v1/admin/prepayment-options` (Manage global options: e.g., "1,000,000 Rial Downpayment")
        *   `POST /api/v1/admin/prepayment-options`
        *   `GET /api/v1/admin/prepayment-options/{prepayment_option_id}`
        *   `PUT /api/v1/admin/prepayment-options/{prepayment_option_id}`
        *   `DELETE /api/v1/admin/prepayment-options/{prepayment_option_id}`
        *   `POST /api/v1/admin/delivery-options/{option_id}/prepayments` (Body: `{ "prepayment_option_id": ... }`)
        *   `DELETE /api/v1/admin/delivery-options/{option_id}/prepayments/{prepayment_option_id}`: Unlink.
    *   **Key Fields (`prepayment_options`):** `amount`, `label`, `is_active`.

---

## Module 4: User, Order & Enrollment Management

**Admin Goal:** To monitor customer activity, manage sales transactions, and oversee student access to purchased products.

**Key Concepts:**
*   **Users (`users`):** Admin view of registered users.
*   **Orders (`orders`, `order_items`):** Records of sales transactions. `product_snapshot` and `billing_address_snapshot` ensure historical accuracy.
*   **Payments (`payments`):** Records of payment attempts and statuses.
*   **Enrollments (`enrollments`):** *Crucial Table*. Tracks a user's access to a specific `product_delivery_option` after purchase. This is the link between a sale and actual provisioning in Moodle/SpotPlayer/IMS.

**Laravel Best Practices:**
*   Use events and listeners for decoupling order processing logic (e.g., `OrderPlaced` event -> `CreateEnrollmentListener`).
*   Queues are essential for listeners that perform external API calls.

### 4.1. User Management (View & Basic Admin Actions)
    *   **Endpoints:**
        *   `GET /api/v1/admin/users` (Paginated, searchable, filterable).
        *   `GET /api/v1/admin/users/{user_id}` (Include addresses, roles, wallet, link to orders/enrollments).
        *   `GET /api/v1/admin/users/{user_id}/orders`
        *   `GET /api/v1/admin/users/{user_id}/enrollments`
        *   (Future: `PUT /api/v1/admin/users/{user_id}/status`, `POST /api/v1/admin/users/{user_id}/roles`)

### 4.2. Order Management (View & Status Updates)
    *   **Endpoints:**
        *   `GET /api/v1/admin/orders` (Filterable by status, user, date range, vendor).
        *   `GET /api/v1/admin/orders/{order_id}` (Include order items, payments, customer info, snapshots).
        *   `PUT /api/v1/admin/orders/{order_id}/status` (Body: `{ "status": "processing", "admin_notes": "..." }`). This should trigger relevant events.

### 4.3. Enrollment Management (`enrollments`)
    *   **Endpoints:**
        *   `GET /api/v1/admin/enrollments` (Filterable by user, product, delivery option, status).
        *   `POST /api/v1/admin/enrollments` (For manual creation by admin – e.g., complimentary access. This should also trigger provisioning logic).
        *   `GET /api/v1/admin/enrollments/{enrollment_id}`
        *   `PUT /api/v1/admin/enrollments/{enrollment_id}` (Update status, access dates, notes. Some status changes might re-trigger provisioning logic).
        *   `POST /api/v1/admin/enrollments/{enrollment_id}/actions/provision` (Explicitly (re)trigger provisioning in external systems like Moodle/SpotPlayer).
        *   `POST /api/v1/admin/enrollments/{enrollment_id}/actions/revoke` (Trigger de-provisioning).
    *   **Key Fields:** `user_id`, `order_item_id`, `product_delivery_option_id`, `enrollment_status`, `access_start_date`, `access_end_date`, `external_enrollment_id`, `provisioning_data`, `notes`.

---

## Module 5: Promotions & Discounts (Basic for Phase 1)

**Admin Goal:** To create and manage discount codes to incentivize purchases.

### 5.1. Discount Management (`discounts`)
    *   **Endpoints:** Standard CRUD for discounts.
        *   `GET /api/v1/admin/discounts`
        *   `POST /api/v1/admin/discounts`
        *   `GET /api/v1/admin/discounts/{discount_id}`
        *   `PUT /api/v1/admin/discounts/{discount_id}`
        *   `DELETE /api/v1/admin/discounts/{discount_id}` (or a status update to deactivate).
    *   **Key Fields:** `name`, `code`, `description`, `type` (e.g., 'percentage', 'fixed'), `value`, `min_order_amount`, `max_discount`, `usage_limit_per_coupon`, `usage_limit_per_user`, `start_date`, `end_date`, `is_active`.
    *   **Focus for Phase 1:** Simple codes. `discount_rules` for complex conditions can be Phase 2.

---

## General Backend Architecture & Workflow Considerations:

*   **Service Layer / Action Classes:** For any logic more complex than basic Eloquent operations, encapsulate it in dedicated classes (e.g., `App\Services\OrderProcessingService`, `App\Actions\Enrollment\ProvisionUserInMoodleAction`). Controllers should remain thin, delegating business logic.
*   **External API Integrations (Moodle, SpotPlayer, IMS):**
    *   Create dedicated service classes for each external API (e.g., `App\Services\MoodleApiService`).
    *   These services will handle HTTP calls, authentication with those APIs, and error handling.
    *   Store API keys and endpoints securely in `.env` and config files.
    *   Enrollment provisioning logic will use these services.
*   **Background Jobs & Queues (Laravel Queues):**
    *   **Critical for:** Sending emails (order confirmations, enrollment details, password resets), provisioning users in external systems, processing large data imports/exports.
    *   This ensures API responses are fast and user experience isn't impacted by slow external processes.
*   **Error Handling & Logging:**
    *   Use consistent API response structures for errors (e.g., your `ApiErrorResponse` and `ApiFailResponse`).
    *   Implement comprehensive logging (Laravel's default logging, or tools like Sentry) for errors, critical events, and admin actions for audit trails.
*   **API Versioning:** Your `/v1/` path is a good start. Plan for future versions if breaking changes become necessary.
*   **Database Transactions:** Use `DB::transaction(function () { ... });` for operations that must either all succeed or all fail (e.g., creating an order and its items, updating inventory).


---

## API Response Structure:

Use the established `ApiSuccessResponse`, `ApiFailResponse`, `ApiErrorResponse` classes.

*   **Success (`ApiSuccessResponse`):** `HTTP 200 OK` or `HTTP 201 Created`.
    *   `message`: Human-readable confirmation.
    *   `data`: The requested/created resource, or state indicators for the client. Can include HATEOAS-style `_links`.
    *   `metadata`: Pagination info, additional context.
*   **Client-Side Errors (`ApiFailResponse`):** `HTTP 400 Bad Request`, `HTTP 422 Unprocessable Entity` (for validation), `HTTP 404 Not Found`, `HTTP 401 Unauthorized`, `HTTP 403 Forbidden`.
    *   `message`: General error message.
    *   `errors`: Specific field errors (for 422) or detailed error information.
*   **Server-Side Errors (`ApiErrorResponse`):** `HTTP 500 Internal Server Error`.
    *   `message`: General error message.
    *   Debug info included only if `config('app.debug')` is true.

**Handling "Redirects" / Flow Control:**
APIs signal next steps to the client via the `data` payload in successful responses, not via HTTP redirects. The client application interprets this data to manage UI flow. Example:
```json
// POST /api/v1/auth/initiate
// Response when password is set (HTTP 200 OK)
{
    "message": "User has set password",
    "data": {
        "login_method": "PASSWORD" // Signal to client to show password form
    },
    "metadata": []
}
```
