# API Documentation: Contact Us Page

This document outlines the API endpoints required to render the "ارتباط با ما" (Contact Us) page and handle user inquiries submitted through the contact form. All endpoints are prefixed with `/api/v1/`.

---

## 1. Page Information

This endpoint retrieves all the necessary contact details and descriptive text to populate the page.

### GET `/api/v1/settings/contact-info`

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/SettingController.php` (New Method)
-   **Response DTO:** `ContactInfoData`
-   **Backend Logic:** An action class should be created to query the `Setting` model for all keys related to contact information (`contact_us_description`, `address_1`, `phone_1`, `map_link_1`, `working_hours`, `social_media_links`, etc.) and assemble them into a single, clean DTO.

**Response Body (`ContactInfoData`):**

```json
{
  "data": {
    "title": "ارتباط با ما",
    "description": "فرصت همکاری با موسسه آموزشی جهاد دانشگاهی استان قزوین همیشه آماده پاسخگویی به سوالات، پیشنهادها و درخواست‌های شما هستیم. از راه‌های زیر می‌توانید با ما در ارتباط باشید:",
    "image_url": "https://api.jedu.ir/media/pages/contact_us_banner.png",
    "locations": [
      {
        "title": "مرکز ۱",
        "address": "آدرس: قزوین، چهارراه ولیعصر، جنب بانک سپه، مرکز شماره یک آموزش های تخصصی جهاد دانشگاهی قزوین",
        "phone": "۰۲۸-۳۳۳۷۶۷۹۷",
        "map_link": "https://maps.google.com/?q=..."
      },
      {
        "title": "مرکز ۲",
        "address": "آدرس: قزوین، خیابان شهید بابایی، کوچه شهید خاکعلی (۲۹)، پلاک ۱۵",
        "phone": "۰۲۸-۳۳۳۶۴۰۴۸",
        "map_link": "https://maps.google.com/?q=..."
      }
    ],
    "working_hours": "ساعت کاری: 8 الی 21",
    "email": "jedu@jdqazvin.ir",
    "social_media_email": "jedu.ir@",
    "social_media_links": [
      { "platform": "instagram", "url": "https://instagram.com/jedu" },
      { "platform": "linkedin", "url": "https://linkedin.com/company/jedu" }
    ]
  }
}
```


## 2. Contact Form Submission

This endpoint handles the submission of the contact form, creating a new ContactUsRequest in the database.

### POST /api/v1/contact-us-requests

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/ContactUsRequestController.php (New)

-   **Request DTO:**  ContactUsRequestCreateData

-   **Backend Logic:** The action class will validate the incoming data and create a new ContactUsRequest record.


**Request Body:**
```json
{
  "full_name": "نام کاربر",
  "email": "user@example.com",
  "phone_number": "09123456789",
  "message_body": "متن پیام کاربر در اینجا قرار میگیرد."
}
```

**Response:**

-   A 201 Created status with a simple JSON success message on successful submission.

-   A 422 Unprocessable Entity status with a standard validation error response if the data is invalid.


**Example Success Response:**
```json
{
  "message": "درخواست شما با موفقیت ثبت شد."
}
```

# API Documentation: Collaboration Page

This document outlines the API endpoints required to render the "فرصت همکاری" (Collaboration Opportunity) page and handle the submission of collaboration requests, including resume uploads. All endpoints are prefixed with `/api/v1/`.

---

## 1. Page Information

This endpoint retrieves all the static text content needed to build the page, such as the title, description, and the lists of conditions and benefits.

### GET `/api/v1/pages/collaboration`

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/PageContentController.php` (Existing)
-   **Response DTO:** `CollaborationPageData`
-   **Backend Logic:** This action queries the `Setting` model for all keys related to the collaboration page (e.g., `collaboration_page_title`, `collaboration_page_description`, `collaboration_conditions_list`, `collaboration_benefits_list`) and assembles them into a single DTO.

**Response Body (`CollaborationPageData`):**

```json
{
  "data": {
    "title": "فرصت همکاری",
    "description": "فرصت همکاری با موسسه آموزشی جهاد دانشگاهی استان قزوین. در جهاد دانشگاهی استان قزوین، ما باور داریم که آموزش با کیفیت، نتیجه همکاری با اساتید توانمند و پرانگیزه است...",
    "image_url": "https://api.jedu.ir/media/pages/collaboration_banner.png",
    "conditions": [
      "داشتن تخصص علمی در یکی از حوزه های آموزشی مورد نیاز",
      "سابقه تدریس یا تجربه مرتبط (ترجیحاً)",
      "توانایی انتقال موثر مفاهیم و ارتباط مناسب با دانشجویان",
      "تعهد به ارتقاء مستمر کیفیت آموزشی"
    ],
    "benefits": [
      "دستمزد رقابتی و منظم",
      "امکان تدریس حضوری و مجازی",
      "همکاری با تیمی پویا و حرفه ای",
      "امکان اخذ گواهی تدریس بعد از چندین دوره",
      "قرارداد رسمی با اساتید"
    ]
  }
}
```



## 2. Collaboration Request Submission

This endpoint handles the submission of the collaboration request form. Because it includes a file, the request must be sent as multipart/form-data.

### POST /api/v1/collaboration-requests

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/CollaborationRequestController.php (New)

-   **Request Type:**  multipart/form-data

-   **Request DTO:**  CollaborationRequestCreateData

-   **Backend Logic:** The action validates the incoming data, uses the media library to securely handle the uploaded resume file, and creates a new CollaborationRequest record in the database.


**Form Fields:**

Field Name

Type

Validation

Description

first_name

string

required, max:255

The applicant's first name.

last_name

string

required, max:255

The applicant's last name.

email

string

required, email

The applicant's email address.

phone_number

string

required, iran_mobile

The applicant's phone number.

resume_file

file

required, file, mimes:pdf,doc,docx, max:2048

The applicant's resume file.

**Response:**

-   A 201 Created status with a simple JSON success message on successful submission.

-   A 422 Unprocessable Entity status with a standard validation error response if the data is invalid.


# API Documentation: About Us Page

This document outlines the API endpoint required to render the "درباره ما" (About Us) page. The design focuses on providing all page-specific content in a single, well-structured response. All endpoints are prefixed with `/api/v1/`.

---

## 1. Page Information

This is the primary endpoint for the "About Us" page. It retrieves all the text, lists, and image gallery URLs needed to build the complete view.

### GET `/api/v1/pages/about-us`

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/PageContentController.php` (Existing)
-   **Response DTO:** `AboutUsPageData`
-   **Backend Logic:** An action class `GetAboutUsPageDataAction` should be created. This action will query the `Setting` model for various keys (e.g., `about_us_story`, `about_us_capabilities`, `about_us_online_course_info`, `about_us_image_gallery`) and also fetch the primary `Category` list to populate the "Active Course Groups" section. All data is then assembled into the `AboutUsPageData` DTO.

**Response Body (`AboutUsPageData`):**

```json
{
  "data": {
    "title": "درباره ما",
    "story": {
      "title": "داستان ما",
      "body": "جهاد دانشگاهی قزوین یکی از معتبرترین مراکز آموزشی استان است که با بیش از ۲۰ سال تجربه در حوزه آموزش‌های تخصصی، همواره در مسیر ارتقاء دانش، نگرش و مهارت منابع انسانی گام برداشته است..."
    },
    "image_gallery_urls": [
      "https://api.jedu.ir/media/about_us/gallery_1.jpg",
      "https://api.jedu.ir/media/about_us/gallery_2.jpg",
      "https://api.jedu.ir/media/about_us/gallery_3.jpg",
      "https://api.jedu.ir/media/about_us/gallery_4.jpg",
      "https://api.jedu.ir/media/about_us/gallery_5.jpg"
    ],
    "capabilities": {
      "title": "توانمندی های ما",
      "items": [
        "گسترده‌ترین شبکه آموزش در سطح استان",
        "برگزاری سالانه بیش از ۶۰۰ دوره عمومی و تخصصی",
        "آموزش بیش از ۲۰۰۰۰ نفر در سال",
        "اجرای دوره‌های آموزشی ویژه کارکنان دولت، شهرداری‌ها و دهیاری‌ها",
        "برخورداری از سامانه یکپارچه آموزش‌های مجازی"
      ]
    },
    "active_course_groups": {
      "title": "گروه های آموزشی فعال",
      "groups": [
        "فنی و مهندسی",
        "مهندسی کامپیوتر",
        "زبان‌های خارجه",
        "علوم پزشکی",
        "فرهنگ و هنر"
      ]
    },
    "online_course_info": {
      "title": "درباره دوره های آنلاین",
      "body": "مدرسه اینورس در ۱۷ آذر ۱۳۸۷ به عنوان اولین مدرسه تخصصی هنرهای دیجیتال در ایران راه اندازی شد. هدف ما ایجاد تغییری جدی در بستر آموزش هنر و دیزاین بود..."
    }
  }
}
```
