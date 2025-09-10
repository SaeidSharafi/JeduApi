# Shop API Implementation Gaps & Admin API Suggestions

This document identifies customer-facing API endpoints that cannot be fully implemented because the corresponding management functionality is missing in the current admin panel (as per `public/docs/collection.json`).

For each identified gap, a high-level suggestion for the required admin API is provided, including endpoints, data structures, and model relationships.

---

## 1. Sliders

- **Shop API Endpoint:** `GET /api/slider`
- **Gap:** There is no "Slider Management" section in the admin API. Admins cannot create, update, or order the sliders that appear on the main page.

### Suggested Admin API: `Admin - Slider Management`

- **Description:** APIs for managing homepage sliders.
- **Model:** A new `Slider` model is required.
  - `Slider` Model Attributes:
    - `id` (Primary Key)
    - `title` (string)
    - `caption` (string, nullable)
    - `image_url` (string)
    - `link` (string)
    - `order` (integer, for sorting)
    - `is_active` (boolean)
    - `created_at`, `updated_at`

- **Admin Endpoints:**
  - `GET /api/admin/sliders`: List all sliders.
  - `POST /api/admin/sliders`: Create a new slider.
    - **Request Body:** `{ "title": "...", "caption": "...", "image_url": "...", "link": "...", "order": 1, "is_active": true }`
  - `GET /api/admin/sliders/{id}`: Get a single slider's details.
  - `PUT /api/admin/sliders/{id}`: Update a slider.
  - `DELETE /api/admin/sliders/{id}`: Delete a slider.

---

## 2. Packages

- **Shop API Endpoint:** `GET /api/package`
- **Gap:** No "Package Management" exists. Admins cannot bundle products (like courses) into packages, set a package price, and manage their visibility.

### Suggested Admin API: `Admin - Package Management`

- **Description:** APIs for creating and managing product packages.
- **Models & Relationships:**
  - A new `Package` model is required.
    - `Package` Model Attributes: `id`, `name`, `slug`, `image`, `price`, `is_active`, `rating`, `badges` (JSON or separate model).
  - A many-to-many relationship between `Package` and `Product` (or `Course`, `Seminar`, etc.) is needed.
    - `package_product` pivot table: `package_id`, `product_id`, `product_type`.

- **Admin Endpoints:**
  - `GET /api/admin/packages`: List all packages.
  - `POST /api/admin/packages`: Create a new package.
    - **Request Body:** `{ "name": "...", "price": 1500000, "is_active": true, "products": [ { "id": 1, "type": "course" }, { "id": 5, "type": "seminar" } ] }`
  - `GET /api/admin/packages/{id}`: Get package details, including its bundled products.
  - `PUT /api/admin/packages/{id}`: Update a package and its bundled products.
  - `DELETE /api/admin/packages/{id}`: Delete a package.

---

## 3. Roadmaps

- **Shop API Endpoint:** `GET /api/roadmap`
- **Gap:** No "Roadmap Management" exists. Admins cannot create learning paths that link to various courses or other resources in a specific order.

### Suggested Admin API: `Admin - Roadmap Management`

- **Description:** APIs for managing learning roadmaps.
- **Models & Relationships:**
  - A new `Roadmap` model: `id`, `title`, `slug`, `picture`, `description`.
  - A new `RoadmapStep` model: `id`, `roadmap_id`, `stepable_id`, `stepable_type` (polymorphic relation to `Course`, `Package`, etc.), `order`.

- **Admin Endpoints:**
  - `GET /api/admin/roadmaps`: List all roadmaps.
  - `POST /api/admin/roadmaps`: Create a new roadmap.
    - **Request Body:** `{ "title": "...", "description": "...", "steps": [ { "order": 1, "stepable_id": 1, "stepable_type": "App\\Models\\Course" } ] }`
  - `GET /api/admin/roadmaps/{id}`: Get roadmap details with its steps.
  - `PUT /api/admin/roadmaps/{id}`: Update a roadmap and its steps.
  - `DELETE /api/admin/roadmaps/{id}`: Delete a roadmap.

---

## 4. Educational Calendar & "Good for Start" Courses

- **Shop API Endpoints:** `GET /api/educational-calendar`, `GET /api/courses/good-for-start`
- **Gap:** These endpoints suggest category-specific metadata that is not currently manageable. There's no admin interface to associate a calendar with a category or to flag certain courses as "Good for Start" within a category.

### Suggested Admin API: `Enhancement to Admin - Category Management`

- **Description:** Add fields to the existing Category model and management API to handle this metadata.
- **Model & Relationships:**
  - **`Category` Model:** Add the following nullable fields:
    - `educational_calendar_image` (string)
    - `educational_calendar_caption` (string)
    - `educational_calendar_download_link` (string)
  - **`category_course` Pivot Table:** Add a new boolean column:
    - `is_good_for_start` (boolean, default: false)

- **Admin Endpoints:**
  - `PUT /api/admin/categories/{id}`: When updating a category, allow the new calendar fields to be set.
  - An endpoint to manage the "good for start" flag for courses within a category is needed. This could be a dedicated endpoint:
    - `POST /api/admin/categories/{categoryId}/courses/toggle-good-for-start`
      - **Request Body:** `{ "course_id": 123, "is_good_for_start": true }`

---

## 5. Cooperation, Partners, Student Stories, Footer, About Us, Contact Us

- **Shop API Endpoints:** `GET /api/cooperation`, `GET /api/partners`, `GET /api/student-stories`, `GET /api/footer`, `GET /api/about-us`, `GET /api/contact-us`, `GET /api/collaboration-us`
- **Gap:** These are all related to managing site-wide content. There is no central "Site Settings" or "Content Management System (CMS)" functionality in the admin API.

### Suggested Admin API: `Admin - Site Content Management`

- **Description:** A set of APIs to manage various content blocks and settings across the site.
- **Models:** This could be implemented with a single, flexible `ContentBlock` model or separate models for each type. A single model is often simpler to start.
  - `ContentBlock` Model: `id`, `key` (e.g., "footer", "about_us", "partners"), `content` (JSON).
- **Admin Endpoints:**
  - `GET /api/admin/content/{key}`: Retrieve the content for a specific section (e.g., `GET /api/admin/content/footer`).
  - `PUT /api/admin/content/{key}`: Update the content for that section.
    - **Example Request Body for `PUT /api/admin/content/footer`:**
      ```json
      {
        "logo": "...",
        "caption": "...",
        "support_link": "...",
        "social_media_links": [ ... ]
      }
      ```
  - For list-based content like **Partners** and **Student Stories**, dedicated CRUD APIs would be better.
    - `Admin - Partner Management`: `GET, POST, PUT, DELETE /api/admin/partners/{id}`
    - `Admin - Student Story Management`: `GET, POST, PUT, DELETE /api/admin/student-stories/{id}`

---

## 6. Blog

- **Shop API Endpoints:** All endpoints under the `/api/blog/*` group.
- **Gap:** The `collection.json` file shows no evidence of a blog system. Admins cannot create posts, manage categories, or view blog-related content.

### Suggested Admin API: `Admin - Blog Management`

- **Description:** A comprehensive set of APIs for managing blog content.
- **Models & Relationships:**
  - `BlogPost` Model: `id`, `title`, `slug`, `content`, `image`, `status` ('draft', 'published'), `published_at`, `time_to_read`.
  - `BlogCategory` Model: `id`, `name`, `slug`.
  - `blog_post_category` pivot table for a many-to-many relationship.
  - `BlogPost` could have a many-to-many relationship with `Course` for the "related courses" feature.

- **Admin Endpoints:**
  - **Posts:**
    - `GET /api/admin/blog/posts`
    - `POST /api/admin/blog/posts`
    - `GET /api/admin/blog/posts/{id}`
    - `PUT /api/admin/blog/posts/{id}`
    - `DELETE /api/admin/blog/posts/{id}`
  - **Categories:**
    - `GET /api/admin/blog/categories`
    - `POST /api/admin/blog/categories`
    - `PUT /api/admin/blog/categories/{id}`
    - `DELETE /api/admin/blog/categories/{id}`
  - **Showcase/Featured Posts:**
    - This could be a boolean flag `is_showcased` on the `BlogPost` model, updatable via `PUT /api/admin/blog/posts/{id}`.

---

## 7. Granular Feature Gaps

This section covers smaller features that are implied by the Shop API but lack a corresponding management mechanism in the admin panel.

### 7.1. Main Page Content Curation

- **Shop API Endpoints:**
  - `GET /api/categories/main-page`
  - `GET /api/courses/recent`
  - `GET /api/courses/most-participant`
- **Gap:** The existence of these endpoints implies that an admin should be able to control *which* categories or courses appear on the main page, or how many are shown. The current admin API for categories and courses doesn't include flags for "Show on Main Page" or a system for ordering them.
- **Suggestion:**
  - **`Category` Model:** Add a boolean flag `show_on_main_page`.
  - **`Course` Model:** Add a boolean flag `show_on_main_page`.
  - **Admin API:** The `PUT /api/admin/categories/{id}` and `PUT /api/admin/courses/{id}` endpoints should be updated to allow setting these new flags.

### 7.2. Webinar Banner

- **Shop API Endpoint:** `GET /api/webinar/banner`
- **Gap:** There is no specific entity for a "Webinar Banner". It's likely intended to be a single, featured webinar. There is no way for an admin to designate one webinar as the "banner" webinar.
- **Suggestion:**
  - **`Webinar` Model (assuming it exists or will be created):** Add a boolean flag `is_banner`.
  - **Admin API:** The admin endpoint for updating a webinar (`PUT /api/admin/seminars/{id}` if a seminar is a webinar) should allow setting this `is_banner` flag. The system should ensure only one webinar can be the banner at a time.

### 7.3. Product Types Dropdown

- **Shop API Endpoint:** `GET /api/product-types/dropdown`
- **Gap:** This endpoint returns a hardcoded-like list of product types. If a new product type is added to the system (e.g., "eBook"), there is no admin interface to manage the list of available types.
- **Suggestion:**
  - This is likely derived from an `Enum` in the backend code (`ProductTypeEnum` for example). While not strictly needing an admin UI, it's a developer-managed list. For full admin control, a `ProductType` model and associated CRUD admin API would be required, but this adds significant complexity. The current approach of managing it in code is acceptable for now, but it's a known limitation.

### 7.4. Advice Request Management

- **Shop API Endpoint:** `POST /api/advice`
- **Gap:** When a user submits their phone number for advice, there is no corresponding admin interface to view, manage, and track the status of these requests.
- **Suggestion:**
  - **Model:** A new `AdviceRequest` model is needed.
    - `AdviceRequest` Attributes: `id`, `phone_number`, `status` ('new', 'contacted', 'resolved'), `notes` (text, nullable), `admin_id` (who handled it).
  - **Admin API:** A new `Admin - Advice Requests` section is needed.
    - `GET /api/admin/advice-requests`: List all requests.
    - `PUT /api/admin/advice-requests/{id}`: Update the status or add notes to a request.
