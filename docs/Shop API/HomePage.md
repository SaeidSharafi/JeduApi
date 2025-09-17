# API Documentation: E-Shop Home Page

This document outlines the public, non-authenticated API endpoints required to render the main home page of the Jedu E-Commerce platform. All endpoints are prefixed with `/api/v1/`.

---

## 1. Page Header & Sliders

### GET `/api/v1/sliders`

Retrieves a list of active, promotional sliders for the main hero section of the home page.

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/SliderController.php` (New)
-   **Response DTO:** `SliderData` Collection

**Response Body:**

```json
{
  "data": [
    {
      "title": "ثبت نام ترم بهار زبان انگلیسی",
      "subtitle": "ترمیک کودک و نوجوان | ترمیک بزرگسال",
      "link": "/categories/english-language",
      "image_url": "https://api.jedu.ir/media/slider/image_1.jpg",
      "button_text": "مشاهده دوره ها"
    }
  ]
}
```

----------

## GET `/api/v1/home-page-content`

Retrieves the entire layout and content for the home page. The frontend will render components based on the `type` of each block returned in the `main_content` array.

-   **Backend Logic:** The `GetHomePageContentAction` will now have two distinct behaviors:
    -   For a `CURATED_LIST` block, it will fetch the specific products/categories using the stored `items` IDs.
    -   For a `DYNAMIC_LIST` block, it will build and execute a new database query based on the `entity_type`, `sort_by`, and `limit` rules defined in the block's content. It will then hydrate the results into the appropriate data structures (`ProductCardData`, etc.).

**Example API Response (Illustrating Both List Types):**

```json
{
  "data": {
    "hero": [
        // ... hero banner block
    ],
    "main_content": [
      {
        "type": "MAIN_CATEGORIES",
        "title": "دسته بندی ها",
        "content": {
          "preset": "default",
          "items": [ /* ... hydrated Category data ... */ ]
        }
      },
      {
        "type": "DYNAMIC_LIST",
        "title": "تازه ترین دوره ها",
        "content": {
          "preset": "product_carousel",
          "//NOTE": "The backend ran a query: Product::orderBy('created_at', 'desc')->limit(8)->get()",
          "items": [
            { "uuid": "prod_newest_1", "name": "دوره جدید پایتون", "price": 3500000, "cover_image_url": "...", "teacher_name": "استاد جدید" },
            { "uuid": "prod_newest_2", "name": "دوره جدید فتوشاپ", "price": 2800000, "cover_image_url": "...", "teacher_name": "استاد هنر" }
          ]
        }
      },
      {
        "type": "CURATED_LIST",
        "title": "پیشنهاد ویژه مدیر",
        "content": {
          "preset": "product_carousel",
          "//NOTE": "The backend fetched specific product IDs:",
          "items": [
            { "uuid": "prod_abc123", "name": "دوره ICDL مقدماتی", "price": 2999000, "original_price": 4999000, "teacher_name": "محسن مردانی" },
            { "uuid": "prod_xyz789", "name": "دوره جامع بورس", "price": 5000000, "teacher_name": "استاد اقتصاد" }
          ]
        }
      },
      {
        "type": "WEBINAR_BANNER",
        // ... webinar banner block
      }
    ]
  }
}
```

----------

## 5. Learning Paths / Roadmaps

### GET /api/v1/categories/roadmaps

Retrieves categories that are designated as "Learning Paths" or "Roadmaps".

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/CategoryController.php (New)

-   **Response DTO:**  RoadmapData Collection

-   **Backend Logic:** This can be implemented by adding a type or is_roadmap flag to the Category model.


**Response Body:**







```
{
  "data": [
    {
      "name": "مارکتینگ",
      "link": "/roadmap/marketing",
      "icon_url": "https://api.jedu.ir/media/roadmap/marketing_icon.svg"
    },
    {
      "name": "تحلیل داده",
      "link": "/roadmap/data-analysis",
      "icon_url": "https://api.jedu.ir/media/roadmap/data_icon.svg"
    }
  ]
}
```

----------

## 6. Partners & Collaborators

### GET /api/v1/collaboration-carousels

Fetches a list of partner/collaborator logos for the "همکاری ها و تفاهم" carousel.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/CollaborationCarouselController.php (New)

-   **Response DTO:**  CollaborationCarouselData Collection


**Response Body:**







```
{
  "data": [
    {
      "name": "Partner A",
      "logo_url": "https://api.jedu.ir/media/partners/partner_a.png",
      "website_url": "https://partner-a.com"
    },
    {
      "name": "Partner B",
      "logo_url": "https://api.jedu.ir/media/partners/partner_b.png",
      "website_url": "https://partner-b.com"
    }
  ]
}
```

----------

## 7. Student Stories (Testimonials)

### GET /api/v1/student-stories/featured

Retrieves a list of featured student stories for the home page.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/StudentStoryController.php (New)

-   **Response DTO:**  StudentStoryData Collection

-   **Backend Logic:** Fetches StudentStory records where is_featured is true.


**Response Body:**







```
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

----------

## 8. Footer

### GET /api/v1/settings/footer

Aggregates all necessary information for the page footer from the Setting model.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/SettingController.php (New)

-   **Response DTO:**  FooterData

-   **Backend Logic:** An action class GetPublicFooterSettingsAction should be created to query the Setting model for all keys related to the footer (footer_description, contact_info_address_1, social_media_links, etc.) and assemble them into a single DTO.


**Response Body:**







```
{
  "data": {
    "logo_url": "https://api.jedu.ir/media/settings/footer_logo.svg",
    "description": "جهاد دانشگاهی قزوین با رویکردی علمی و عملی، از آموزش تا اشتغال همراه شماست...",
    "support_email": "jedu@jdqazvin.ir",
    "addresses": [
      {
        "title": "آدرس مرکز ۱",
        "address": "قزوین، چهارراه پاستور...",
        "phone": "(028)33376797-9"
      }
    ],
    "social_media": [
      { "platform": "instagram", "url": "https://instagram.com/jedu" },
      { "platform": "linkedin", "url": "https://linkedin.com/company/jedu" }
    ],
    "link_columns": [
      {
        "title": "راهنمای خرید دوره",
        "links": [
          { "text": "درباره ما", "url": "/about-us" },
          { "text": "تماس با ما", "url": "/contact-us" }
        ]
      }
    ]
  }
}
```
