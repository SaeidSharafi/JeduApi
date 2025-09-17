
# API Documentation: Product Detail Page (PDP) - Course

This document details the API endpoints required to render the course detail page. It includes authenticated and unauthenticated endpoints for fetching data and submitting user requests. All endpoints are prefixed with `/api/v1/`.

---

## 1. Main Course Data

This is the primary endpoint for the PDP. It fetches all the necessary data to build the initial view of the page.

### GET `/api/v1/products/{uuid}`

Retrieves the complete, detailed information for a single sellable product (in this case, a course) using its public UUID.

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/ProductController.php` (Extend existing)
-   **Response DTO:** `ProductDetailData`
-   **Backend Logic:** Fetches the `Product` by its `uuid` and eager-loads its relations: `productable` (the `Course` model), `vendor`, `reviews` (for rating calculation), `teachers`, and crucially, `productDeliveryOptions`.

**URL Parameter:**

-   `{uuid}` (string, required): The UUID of the product.

**Response Body (`ProductDetailData`):**

```json
{
  "data": {
    "uuid": "prod_course_python101",
    "name": "برنامه نویسی پایتون",
    "vendor_name": "جهاد دانشگاهی قزوین",
    "short_description": "لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم از صنعت چاپ و با استفاده از طراحان گرافیک است...",
    "image_gallery_urls": [
      "https://api.jedu.ir/media/products/py1.jpg",
      "https://api.jedu.ir/media/products/py2.jpg"
    ],
    "rating": {
      "average": 5.0,
      "count": 24
    },
    "key_info": {
      "duration_hours": 4,
      "format": "حضوری | آنلاین",
      "certificate": "ارائه مدرک معتبر"
    },
    "syllabus": [
      { "title": "مقدمه و نصب", "topics": ["نصب پایتون", "اجرای اولین کد"] },
      { "title": "متغیرها و انواع داده", "topics": ["اعداد", "رشته ها"] }
    ],
    "achievements": [
      "لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم...",
      "با استفاده از طراحان گرافیک است..."
    ],
    "teachers": [
      {
        "uuid": "teacher_malihe_m",
        "name": "ملیحه محمدی",
        "title": "مدرس دوره",
        "image_url": "https://api.jedu.ir/media/teachers/malihe_m.jpg"
      }
    ],
    "target_audience": "این دوره برای توسعه دهندگان وب...",
    "prerequisites": [
      "آشنایی با مفاهیم پایه برنامه نویسی",
      "علاقه به حل مسئله"
    ],
    "certificate_info": {
      "title": "گواهی پایان دوره",
      "description": "پس از اتمام موفقیت آمیز دوره، گواهی معتبر جهاد دانشگاهی به شما اعطا خواهد شد.",
      "image_url": "https://api.jedu.ir/media/certificate_sample.jpg"
    },
    "faqs": [
      {
        "question": "آیا در صورت غیبت، جلسه ضبط شده در اختیارم قرار میگیرد؟",
        "answer": "بله، تمامی جلسات ضبط شده و در پنل کاربری شما قابل دسترس خواهد بود."
      }
    ],
    "delivery_options": [
      {
        "id": 101,
        "name": "حضوری استاد مردانی",
        "price": 2700000,
        "pre_payment_price": 700000
      },
      {
        "id": 102,
        "name": "آنلاین استاد محمدی",
        "price": 2500000,
        "pre_payment_price": 600000
      }
    ]
  }
}
```


## 2. User Actions

### POST /api/v1/advice-requests

Submits a user's phone number to request a private consultation for a specific course. This corresponds to the "دریافت مشاوره تخصصی" modal.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/AdviceRequestController.php (New)

-   **Request DTO:**  AdviceRequestCreateData

-   **Backend Logic:** Creates a new record (e.g., in a advice_requests table) logging the user's phone number and the product they are interested in.


**Request Body:**
```json
{
  "phone_number": "09123456789",
  "product_uuid": "prod_course_python101"
}
```


**Response:**

-   201 Created on success with a simple success message.


----------

## 3. Related & Supporting Content

### GET /api/v1/products/{uuid}/related

Fetches a list of related courses, displayed in the "دوره های مرتبط" carousel.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/ProductController.php (New Method)

-   **Response DTO:**  ProductCardData Collection

-   **Backend Logic:** Finds products that share the same categories or teacher as the product specified in the UUID.


**Example Usage:**  GET /api/v1/products/prod_course_python101/related  
**Response Body:** A collection of ProductCardData objects.

### GET /api/v1/student-stories

Fetches student testimonials related to the current course for the "نظرات فراگیران این دوره" section.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/StudentStoryController.php (Existing)

-   **Response DTO:**  StudentStoryData Collection

-   **Query Parameters:**

    -   product_uuid (string, required): The UUID of the product.


**Example Usage:**  GET /api/v1/student-stories?product_uuid=prod_course_python101  
**Response Body:** A collection of StudentStoryData objects.

### GET /api/v1/teachers/{uuid}

Retrieves the detailed profile for a specific teacher. This is called when a user clicks on a teacher's card.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/TeacherController.php (New)

-   **Response DTO:**  TeacherDetailData


**URL Parameter:**

-   {uuid} (string, required): The UUID of the teacher.


**Response Body:**
```json
{
  "data": {
    "uuid": "teacher_malihe_m",
    "name": "ملیحه محمدی",
    "image_url": "https://api.jedu.ir/media/teachers/malihe_m.jpg",
    "bio": "لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم از صنعت چاپ و با استفاده از طراحان گرافیک است...",
    "stats": {
      "active_courses": 3,
      "review_count": 24,
      "average_rating": 5.0
    },
    "social_links": {
      "linkedin": "https://linkedin.com/in/malihe-mohammadi"
    }
  }
}
```


## 4. Reusable Endpoints

The following endpoints, already defined for other pages, should be reused here.

### Partners & Collaborators

-   **Endpoint:**  GET /api/v1/collaboration-carousels


### Footer

-   **Endpoint:**  GET /api/v1/settings/footer
