# API Documentation: PLP - Webinars & Events (Seminars)

This document outlines the public API endpoints required to render the main listing page for webinars and events, which correspond to the `Seminar` product type in the backend. All endpoints are prefixed with `/api/v1/`.

---

## 1. Page Content

This endpoint provides the static content needed for the page's header, such as the title and description.

### GET `/api/v1/pages/webinars`

Retrieves the main content for the webinars listing page.

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/PageContentController.php` (New)
-   **Response DTO:** `PageContentData`
-   **Backend Logic:** This can be sourced from a simple key-value store or a dedicated `Page` model where the key is `webinars`.

**Response Body:**

```json
{
"data": {
"title": "وبینارها و رویدادها",
"description": "رایانه‌ها نیروی محرکه پیشرفت و سرعت بخشیدن به امور کاری در هر زمینه‌ای هستند. برای مطالعه و به‌کارگیری علوم کامپیوتر، کنترل تعامل بین توسعه نرم افزار و سخت افزار کامپیوتر و الگوریتم هایی که آنها را اجرا می کنند، یادگیری مهارتهای مرتبط ضروری است...",
"image_url": "https://api.jedu.ir/media/pages/webinars_banner.png"
}
}
```

## 2. Seminar Listing, Filtering & Sorting

This single, robust endpoint will serve all dynamic lists on this page, including "Newest Webinars" and "Nearing Capacity," by using different query parameters. It reuses the primary products endpoint, filtered for the Seminar type.

### GET /api/v1/products

Retrieves a paginated list of products, configured to fetch only Seminars.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/ProductController.php (Existing)

-   **Response DTO:**  SeminarCardData Collection (Paginated)


**Mandatory Query Parameter:**

-   filter[productable_type] (string, required): Must be set to seminar.


**Other Key Query Parameters:**

-   sort (string, optional): The sorting criteria.

    -   -start_date: For "جدیدترین وبینارها" (Newest Webinars), sorting by the upcoming date.

    -   capacity_remaining: For "در حال اتمام ظرفیت" (Nearing Capacity). This requires a custom sort appender in the spatie/laravel-query-builder implementation.

-   filter[is_featured] (boolean, optional): To retrieve a specific featured webinar for the banner display (e.g., filter[is_featured]=true).

-   filter[category_slug] (string, optional): To filter webinars by a specific category (e.g., computer-science).

-   page (integer, optional): The page number for pagination.

-   limit (integer, optional): Number of items per page.


**Response Fields (SeminarCardData):**

-   uuid: Unique identifier for the seminar product.

-   title: The title of the webinar.

-   teacher_name: Full name of the presenting teacher.

-   teacher_image_url: URL for the teacher's profile picture.

-   teacher_rating: The teacher's average rating.

-   start_date_formatted: A human-readable start date (e.g., "۲۲ اردیبهشت ۱۴۰۳").

-   excerpt: A short description or excerpt of the webinar content.

-   cover_image_url: URL for the webinar's main banner image (used for featured items).


**Example Usage:**

1.  **For the "جدیدترین وبینارها" (Newest Webinars) List:**  
    GET /api/v1/products?filter[productable_type]=seminar&sort=-start_date&limit=4

2.  **For the Featured Webinar Banner:**  
    GET /api/v1/products?filter[productable_type]=seminar&filter[is_featured]=true&limit=1

3.  **For the "در حال اتمام ظرفیت" (Nearing Capacity) Grid:**  
    GET /api/v1/products?filter[productable_type]=seminar&sort=capacity_remaining&limit=6


**Example Response Body:**

```json
{
  "data": [
    {
      "uuid": "sem_xyz789",
      "title": "تکنولوژی های هوش مصنوعی",
      "teacher_name": "حسین محمدی",
      "teacher_image_url": "https://api.jedu.ir/media/teachers/h_mohammadi.jpg",
      "teacher_rating": 5.0,
      "start_date_formatted": "۲۲ اردیبهشت ۱۴۰۳",
      "excerpt": "رایانه‌ها نیروی محرکه پیشرفت و سرعت بخشیدن به امور کاری در هر زمینه‌ای هستند...",
      "cover_image_url": "https://api.jedu.ir/media/seminars/ai_cover.jpg"
    }
  ],
  "links": {
    "first": "https://api.jedu.ir/v1/products?page=1",
    "last": "https://api.jedu.ir/v1/products?page=2",
    "prev": null,
    "next": "https://api.jedu.ir/v1/products?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 2,
    "path": "https://api.jedu.ir/v1/products",
    "per_page": 4,
    "to": 4,
    "total": 8
  }
}
```

## 3. Reusable Endpoints

The following endpoints, already designed for other pages, should be reused here.

### Category Filters

To populate the filter chips (e.g., "علوم رایانه"), use the main categories endpoint.

-   **Endpoint:**  GET /api/v1/categories/main-page


### Footer

The standard site footer is identical on this page.

-   **Endpoint:**  GET /api/v1/settings/footer
