
# API Documentation: Blog Index Page

This document details the public API endpoints required to build the main blog listing page. The design focuses on a primary, filterable endpoint for posts and a separate endpoint for categories. All endpoints are prefixed with `/api/v1/`.

---

## 1. Blog Categories

This endpoint fetches the list of categories used to populate the filter chips below the featured posts carousel.

### GET `/api/v1/blog-categories`

Retrieves a list of all blog categories.

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/BlogCategoryController.php` (New)
-   **Response DTO:** `BlogCategoryData` Collection
-   **Backend Logic:** Fetches all `BlogCategory` records.

**Response Body:**

```json
{
  "data": [
    {
      "slug": "programming",
      "name": "برنامه نویسی",
      "icon_url": "https://api.jedu.ir/media/blog/categories/programming.svg"
    },
    {
      "slug": "foreign-languages",
      "name": "زبان های خارجی",
      "icon_url": "https://api.jedu.ir/media/blog/categories/languages.svg"
    }
  ]
}
```

## 2. Blog Post Listing

This is the main, versatile endpoint for the blog page. It serves the featured carousel, the main paginated list, and the category-specific carousels through the use of query parameters.

### GET /api/v1/blog-posts

Retrieves a paginated list of blog posts, with support for filtering, searching, and sorting.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/BlogPostController.php (New)

-   **Response DTO:**  BlogPostCardData Collection (Paginated)


**Query Parameters:**

-   filter[is_featured] (boolean, optional): Set to true to get only the featured posts for the top carousel.

-   filter[category_slug] (string, optional): Filters the list to posts within a specific category slug (e.g., programming).

-   filter[title] (string, optional): The search term from the search input.

-   sort (string, optional): The sorting order.

    -   -published_at: For "آخرین مقالات" (Latest Articles).

    -   -popularity_score: For "محبوب ترین مقالات" (Most Popular Articles). (Requires a popularity_score or view_count field on the BlogPost model).

-   page (integer, optional): The page number for pagination.

-   limit (integer, optional): The number of items to return per page.


**Response Fields (BlogPostCardData):**

-   slug: The URL-friendly slug for linking to the detail page.

-   title: The title of the blog post.

-   cover_image_url: URL for the main post image.

-   average_rating: The calculated average rating.

-   read_time_minutes: The estimated time to read the article.

-   published_at_formatted: A human-readable publication date (e.g., "۵ اردیبهشت ۱۴۰۳").

-   primary_category_name: The name of the main category for display as a tag.


**Example Usage:**

1.  **For the Featured Posts Carousel (Top of page):**  
    GET /api/v1/blog-posts?filter[is_featured]=true&limit=5

2.  **For the Main List ("محبوب ترین مقالات اخیر"):**  
    GET /api/v1/blog-posts?sort=-popularity_score

3.  **When a User Filters by Category ("برنامه نویسی"):**  
    GET /api/v1/blog-posts?filter[category_slug]=programming&sort=-published_at

4.  **For the "مقالات مرتبط با برنامه نویسی" Carousel:**  
    GET /api/v1/blog-posts?filter[category_slug]=programming&limit=5


**Example Paginated Response:**
```json
{
  "data": [
    {
      "slug": "intro-to-english-grammar",
      "title": "معرفی چند پیشوند مهم و کاربردی زبان انگلیسی",
      "cover_image_url": "https://api.jedu.ir/media/blog/posts/english_prefix.jpg",
      "average_rating": 4.5,
      "read_time_minutes": 13,
      "published_at_formatted": "۵ اردیبهشت ۱۴۰۳",
      "primary_category_name": "زبان های خارجی"
    }
  ],

  "links": {
    "first": "https://api.jedu.ir/v1/blog-posts?page=1",
    "last": "https://api.jedu.ir/v1/blog-posts?page=5",
    "prev": null,
    "next": "https://api.jedu.ir/v1/blog-posts?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "https://api.jedu.ir/v1/blog-posts",
    "per_page": 6,
    "to": 6,
    "total": 30
  }
}
```


# API Documentation: Blog Post Detail Page

This document details the API endpoints required to render a single blog post page. It includes the main endpoint for fetching the post's content and reuses existing endpoints for sidebars and related content. All endpoints are prefixed with `/api/v1/`.

---

## 1. Main Blog Post Data

This is the primary endpoint for the page. It retrieves all content related to a specific blog post using its unique, URL-friendly slug.

### GET `/api/v1/blog-posts/{slug}`

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/BlogPostController.php` (New Method)
-   **Response DTO:** `BlogPostDetailData`
-   **Backend Logic:** This action will fetch the `BlogPost` by its `slug` and eager-load its relationships, including `author` (Staff), `categories`, and most importantly, `mainProductable` for the CTA banner and `courses` (or other productables) for the "Related Courses" carousel.

**URL Parameter:**

-   `{slug}` (string, required): The slug of the blog post (e.g., `how-to-weave-rugs`).

**Response Body (`BlogPostDetailData`):**

```json
{
  "data": {
    "title": "آموزش گلیم بافی",
    "cover_image_url": "https://api.jedu.ir/media/blog/posts/rug_weaving.jpg",
    "video_url": "https://api.jedu.ir/media/blog/videos/rug_weaving.mp4",
    "body": "<h1>مقدمه ای بر گلیم بافی</h1><p>لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم از صنعت چاپ...</p>",
    "published_at_formatted": "۵ اردیبهشت ۱۴۰۳",
    "read_time_minutes": 13,
    "average_rating": 5.0,
    "author": {
      "name": "محسن مردانی",
      "image_url": "https://api.jedu.ir/media/staff/mohsen_m.jpg"
    },
    "categories": [
      {
        "name": "مشاغل خانگی",
        "slug": "home-business"
      },
      {
        "name": "هنر",
        "slug": "art"
      }
    ],
    "headlines": [
      "معرفی گلیم بافی",
      "انواع تکنیک های بافت",
      "بافت چاکدار"
    ],
    "main_related_course": {
      "uuid": "prod_course_rug101",
      "name": "دوره ی آموزش گلیم بافی رو ببین و حرفه ای شو",
      "link": "/products/prod_course_rug101"
    },
    "related_courses": [
      {
        "uuid": "prod_abc123",
        "name": "دوره ICDL مقدماتی",
        "cover_image_url": "https://api.jedu.ir/media/product/cover_1.jpg",
        "average_rating": 4.5,
        "price": 2999000
      },
      {
        "uuid": "prod_def456",
        "name": "دوره Business Model",
        "cover_image_url": "https://api.jedu.ir/media/product/cover_2.jpg",
        "average_rating": 4.8,
        "price": 3500000
      }
    ]
  }
}
```

## 2. Sidebar Content (Recent & Popular Posts)

The sidebars for "مطالب اخیر" (Recent Posts) and "مطالب پر بازدید" (Popular Posts) will be populated by reusing the existing blog post list endpoint with specific query parameters.

### GET /api/v1/blog-posts

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/BlogPostController.php (Existing)

-   **Response DTO:**  BlogPostCardData Collection


**Example Usage:**

1.  **For "مطالب اخیر" (Recent Posts):**  
    GET /api/v1/blog-posts?sort=-published_at&limit=5

2.  **For "مطالب پر بازدید" (Popular Posts):**  
    GET /api/v1/blog-posts?sort=-popularity_score&limit=5


**Response Body (for each request):**  
A collection of BlogPostCardData objects, as defined in the Blog Index Page documentation.
