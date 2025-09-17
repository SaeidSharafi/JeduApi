# API Documentation: Product Listing Page (PLP) - Courses

This document details the public, non-authenticated API endpoints required to build the category-specific product listing pages, such as the "دوره‌های علوم رایانه" (Computer Science Courses) page. All endpoints are prefixed with `/api/v1/`.

---

## 1. Category Details

This is the primary endpoint to fetch all information about a specific category, which is used to build the page's header, banner, and description.

### GET `/api/v1/categories/{slug}`

Retrieves the detailed information for a single category using its URL-friendly slug.

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/CategoryController.php` (Extend existing)
-   **Response DTO:** `CategoryDetailData`
-   **Backend Logic:** This fetches a `Category` model by its `slug`. It should also eager-load its `children` categories to populate the sub-category filter section.

**URL Parameter:**

-   `{slug}` (string, required): The slug of the category (e.g., `computer-science`).

**Response Body:**

```json
{
  "data": {
    "name": "دوره‌های علوم رایانه",
    "description": "رایانه‌ها نیروی محرکه پیشرفت و سرعت بخشیدن به امور کاری در هر زمینه‌ای هستند...",
    "image_url": "https://api.jedu.ir/media/category/banner/cs_banner.png",
    "educational_calendar_link": "/files/cs-calendar.pdf",
    "children": [
      {
        "name": "برنامه نویسی",
        "slug": "programming"
      },
      {
        "name": "شبکه",
        "slug": "networking"
      }
    ]
  }
}
```


## 2. Product Listing & Filtering

This is the main endpoint for fetching, sorting, and filtering courses within a specific category. It is designed to be flexible and handle all product grid requirements, including the "Recent Courses" and the main filtered list.

### GET /api/v1/products

Retrieves a paginated list of products, primarily used for course listings. It leverages spatie/laravel-query-builder for powerful filtering and sorting.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/ProductController.php (Extend existing)

-   **Response DTO:**  ProductCardData Collection (Paginated)


**Query Parameters:**

-   filter[category_slug] (string, required): The slug of the category to filter by.

-   sort (string, optional): Sorting order.

    -   -created_at: For "جدیدترین" (Newest).

    -   -enrolment_count: For "محبوبترین" (Most Popular).

-   filter[delivery_type] (string, optional): Filters by course type.

    -   online: For "آنلاین".

    -   in_person: For "حضوری".

-   filter[level] (string, optional): Filters by course level, stored in details_json.

    -   beginner: For "مقدماتی".

    -   intermediate: For "متوسط".

    -   advanced: For "پیشرفته".

-   page (integer, optional): The page number for pagination.

-   limit (integer, optional): Number of items per page. Defaults to 12.


**Response Fields (ProductCardData) are the same as the Home Page.**

**Example Usage:**

1.  **For the "دوره های اخیر" (Recent Courses) Carousel:**  
    GET /api/v1/products?filter[category_slug]=computer-science&sort=-created_at&limit=5

2.  **For the Main Grid (Default - Newest):**  
    GET /api/v1/products?filter[category_slug]=computer-science&sort=-created_at

3.  **For a Filtered and Paginated Request:**  
    GET /api/v1/products?filter[category_slug]=computer-science&sort=-enrolment_count&filter[level]=beginner&page=2


**Example Paginated Response:**
```json
{
  "data": [
    {
      "uuid": "prod_abc123",
      "name": "دوره ICDL مقدماتی",
      "product_type": "Course",
      "teacher_name": "محسن مردانی",
      "cover_image_url": "https://api.jedu.ir/media/product/cover_1.jpg",
      "average_rating": 4.5,
      "tags": ["گواهی معتبر", "محبوب فراگیران"],
      "status": "فعال",
      "price": 2999000,
      "original_price": 4999000,
      "discount_percentage": 50
    }
  ],
  "links": {
    "first": "https://api.jedu.ir/v1/products?page=1",
    "last": "https://api.jedu.ir/v1/products?page=3",
    "prev": null,
    "next": "https://api.jedu.ir/v1/products?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "path": "https://api.jedu.ir/v1/products",
    "per_page": 12,
    "to": 12,
    "total": 35
  }
}
```


## 3. Recommended Courses ("Good for Start")

This endpoint fetches a curated list of products recommended for beginners within a specific category.

### GET /api/v1/products/good-for-start

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/ProductController.php (New method)

-   **Response DTO:**  ProductCardData Collection

-   **Backend Logic:** The action for this endpoint will find all products associated with the given category that are also marked as is_good_for_start (a flag on the Product or related Category model).


**Query Parameters:**

-   category_slug (string, required): The slug of the parent category.


**Example Usage:**  
GET /api/v1/products/good-for-start?category_slug=computer-science

**Response Body:**  
(The response will be a non-paginated collection of ProductCardData objects, similar to the data array in the examples above).

----------

## 4. Student Stories by Category

This endpoint fetches testimonials specifically related to the courses within the current category.

### GET /api/v1/student-stories

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/StudentStoryController.php (Extend existing)

-   **Response DTO:**  StudentStoryData Collection

-   **Backend Logic:** The action will receive a category slug, find all Product IDs within that category, and then fetch StudentStory records associated with those products.


**Query Parameters:**

-   category_slug (string, required): The slug of the category.


**Example Usage:**  
GET /api/v1/student-stories?category_slug=computer-science

**Response Body:**
```json
{
  "data": [
    {
      "student_name": "آذین آذرکار",
      "course_name": "دوره ICDL مقدماتی",
      "student_image_url": "https://api.jedu.ir/media/stories/azin.jpg",
      "story_excerpt": "لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم از صنعت چاپ و با استفاده از طراحان گرافیک است..."
    }
  ]
}
```
