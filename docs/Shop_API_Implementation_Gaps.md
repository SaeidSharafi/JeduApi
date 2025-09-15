# Shop API Implementation Gaps & Admin API Suggestions

This document identifies customer-facing API endpoints that cannot be fully implemented because the corresponding management functionality is missing in the current admin panel (as per `public/docs/collection.json`).

For each identified gap, a high-level suggestion for the required admin API is provided, including endpoints, data structures, and model relationships.

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

### 7.3. Product Types Dropdown

- **Shop API Endpoint:** `GET /api/product-types/dropdown`
- **Gap:** This endpoint returns a hardcoded-like list of product types. If a new product type is added to the system (e.g., "eBook"), there is no admin interface to manage the list of available types.
- **Suggestion:**
  - This is likely derived from an `Enum` in the backend code (`ProductTypeEnum` for example). While not strictly needing an admin UI, it's a developer-managed list. For full admin control, a `ProductType` model and associated CRUD admin API would be required, but this adds significant complexity. The current approach of managing it in code is acceptable for now, but it's a known limitation.
