# JeduShop Customer API Documentation

This document provides a comprehensive overview of the customer-facing (Shop) API endpoints for JeduShop. The API is designed to be RESTful and uses standard HTTP verbs and status codes.

**Authentication:**
- Endpoints marked with `[NoAuth]` do not require authentication.
- Endpoints marked with `[Auth]` require a bearer token in the `Authorization` header. The authentication middleware is `auth:user`.

---

## Main Page

### GET `/api/categories`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a list of all categories with their children and logos.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Programming",
        "slug": "programming",
        "logo_url": "https://api.jedu.ir/storage/categories/logos/programming.svg",
        "children": [
          {
            "id": 10,
            "name": "PHP",
            "slug": "php",
            "logo_url": "https://api.jedu.ir/storage/categories/logos/php.svg"
          }
        ]
      }
    ]
  }
  ```

### GET `/api/categories/main-page`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a curated list of categories for the main page.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Programming",
        "slug": "programming",
        "logo_url": "https://api.jedu.ir/storage/categories/logos/programming.svg"
      }
    ]
  }
  ```

### GET `/api/product-types/dropdown`

- **Authentication:** `[NoAuth]`
- **Description:** Returns all product types (e.g., course, seminar) for use in dropdowns.
- **Example Response:**
  ```json
  {
    "data": [
      { "value": "course", "label": "Course" },
      { "value": "seminar", "label": "Seminar" },
      { "value": "book", "label": "Book" }
    ]
  }
  ```

### GET `/api/search`

- **Authentication:** `[NoAuth]`
- **Description:** Searches for products based on a filter and type.
- **Query Parameters:**
  - `filter` (string): The search term.
  - `type` (string): The product type (e.g., `course`, `seminar`).
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Advanced Laravel",
        "type": "course",
        "image_url": "https://api.jedu.ir/storage/products/advanced-laravel.jpg"
      }
    ]
  }
  ```

### GET `/api/slider`

- **Authentication:** `[NoAuth]`
- **Description:** Returns all active sliders for the main page.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "title": "New Course Release",
        "caption": "Check out our new course on advanced testing.",
        "image_url": "https://api.jedu.ir/storage/sliders/slide1.jpg",
        "link": "/courses/advanced-testing"
      }
    ]
  }
  ```

### GET `/api/courses/recent`

- **Authentication:** `[NoAuth]`
- **Description:** Returns the newest courses.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Advanced Laravel",
        "picture": "https://api.jedu.ir/storage/courses/advanced-laravel.jpg",
        "is_active": true,
        "rating": 4.8,
        "tags": ["PHP", "Laravel"],
        "price": 500000,
        "teacher_name": "Saeid Sharafi",
        "discount": {
          "percentage": 10,
          "final_price": 450000
        }
      }
    ]
  }
  ```

### GET `/api/courses/most-participant`

- **Authentication:** `[NoAuth]`
- **Description:** Returns the most popular courses based on participant count.
- **Example Response:** (Same structure as `/api/courses/recent`)

### GET `/api/webinar/banner`

- **Authentication:** `[NoAuth]`
- **Description:** Returns information for the main webinar banner.
- **Example Response:**
  ```json
  {
    "data": {
      "title": "Live Q&A with Experts",
      "picture": "https://api.jedu.ir/storage/webinars/banner.jpg",
      "teacher_name": "Jane Doe",
      "datetime": "2025-10-01T19:00:00Z",
      "caption": "Join us for a live Q&A session.",
      "link": "/webinars/live-qa"
    }
  }
  ```

### GET `/api/webinar/recent`

- **Authentication:** `[NoAuth]`
- **Description:** Returns recent webinars.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "title": "Introduction to TDD",
        "teacher_name": "John Smith",
        "teacher_picture": "https://api.jedu.ir/storage/teachers/john-smith.jpg",
        "teacher_rating": 4.9,
        "date": "2025-09-20",
        "link": "/webinars/intro-to-tdd"
      }
    ]
  }
  ```

### GET `/api/package`

- **Authentication:** `[NoAuth]`
- **Description:** Returns packages for the main page.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Frontend Developer Bundle",
        "is_active": true,
        "rating": 4.7,
        "badges": ["Best Value"],
        "price": 1500000,
        "discount": {
          "percentage": 20,
          "final_price": 1200000
        },
        "image": "https://api.jedu.ir/storage/packages/frontend-bundle.jpg",
        "link": "/packages/frontend-developer"
      }
    ]
  }
  ```

### GET `/api/roadmap`

- **Authentication:** `[NoAuth]`
- **Description:** Returns available learning roadmaps.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "title": "Full-Stack Developer Roadmap",
        "picture": "https://api.jedu.ir/storage/roadmaps/full-stack.png",
        "link": "/roadmaps/full-stack-developer"
      }
    ]
  }
  ```

### GET `/api/educational-calendar`

- **Authentication:** `[NoAuth]`
- **Description:** Returns educational calendar information.
- **Example Response:**
  ```json
  {
    "data": {
      "title": "Fall 2025 Semester",
      "image": "https://api.jedu.ir/storage/calendars/fall-2025.jpg",
      "caption": "Key dates for the fall semester.",
      "download_link": "https://api.jedu.ir/storage/calendars/fall-2025.pdf"
    }
  }
  ```

### GET `/api/cooperation`

- **Authentication:** `[NoAuth]`
- **Description:** Returns information about cooperation opportunities.
- **Example Response:**
  ```json
  {
    "data": {
      "title": "Partner with JeduShop",
      "image": "https://api.jedu.ir/storage/cooperation/partner-with-us.jpg",
      "caption": "Become a content partner and reach a wider audience.",
      "link": "/cooperation"
    }
  }
  ```

### GET `/api/partners`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a list of partner logos.
- **Example Response:**
  ```json
  {
    "data": [
      { "name": "Partner A", "image_link": "https://api.jedu.ir/storage/partners/partner-a.svg" },
      { "name": "Partner B", "image_link": "https://api.jedu.ir/storage/partners/partner-b.svg" }
    ]
  }
  ```

### GET `/api/student-stories`

- **Authentication:** `[NoAuth]`
- **Description:** Returns student success stories.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "id": 1,
        "title": "From Zero to Hero",
        "course_name": "Complete Web Development",
        "student_image": "https://api.jedu.ir/storage/students/story1.jpg",
        "caption": "This course changed my life and helped me land my dream job."
      }
    ]
  }
  ```

### GET `/api/footer`

- **Authentication:** `[NoAuth]`
- **Description:** Returns all data needed for the website footer.
- **Example Response:**
  ```json
  {
    "data": {
      "logo": "https://api.jedu.ir/storage/logos/footer-logo.svg",
      "caption": "Your partner in modern education.",
      "support_link": "/contact-us",
      "support_email_address": "support@jedu.ir",
      "addresses": [
        { "city": "Tehran", "address": "Main St, 123" }
      ],
      "social_media_links": [
        { "platform": "instagram", "link": "https://instagram.com/jedushop" }
      ],
      "items": [
        { "title": "About Us", "link": "/about-us" },
        { "title": "Blog", "link": "/blog" }
      ],
      "enamad_logo": "https://api.jedu.ir/storage/logos/enamad.png"
    }
  }
  ```

---

## PLP (Product Listing Page)

### GET `/api/categories/{slug}`

- **Authentication:** `[NoAuth]`
- **Description:** Returns details for a specific category.
- **Example Response:**
  ```json
  {
    "data": {
      "slug": "programming",
      "title": "Programming",
      "description": "Learn to code from the best in the industry.",
      "image_link": "https://api.jedu.ir/storage/categories/banners/programming.jpg",
      "calendar_link": "/educational-calendar?category=programming"
    }
  }
  ```

### GET `/api/courses`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a paginated and filterable list of courses.
- **Query Parameters:**
  - `category_slug` (string): Filter by category slug.
  - `sort_by` (string): `recent`, `popular`, `price_asc`, `price_desc`.
  - `filters` (object): Key-value pairs for filtering (e.g., `level=beginner`).
  - `page` (integer): The page number for pagination.
- **Example Response:** (Same structure as `/api/courses/recent` on the main page, with pagination metadata)
  ```json
  {
    "data": [
      {
        "slug": "advanced-laravel",
        "name": "Advanced Laravel",
        "picture": "https://api.jedu.ir/storage/courses/advanced-laravel.jpg",
        "is_active": true,
        "rating": 4.8,
        "tags": ["PHP", "Laravel"],
        "price": 500000,
        "teacher_name": "Saeid Sharafi",
        "discount": {
          "percentage": 10,
          "final_price": 450000
        }
      }
    ],
    "links": {
      "first": "...",
      "last": "...",
      "prev": null,
      "next": "..."
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 5,
      "path": "https://api.jedu.ir/api/courses",
      "per_page": 15,
      "to": 15,
      "total": 75
    }
  }
  ```

### GET `/api/courses/good-for-start`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a list of courses recommended for beginners in a specific category.
- **Query Parameters:**
  - `category_slug` (string): The category to get recommendations for.
- **Example Response:** (Same structure as `/api/courses/recent`)

### GET `/api/student-stories`

- **Authentication:** `[NoAuth]`
- **Description:** Returns student stories for a specific category.
- **Query Parameters:**
  - `categorySlug` (string): The slug of the category.
- **Example Response:** (Same structure as `/api/student-stories` on the main page)

---

## PDP (Product Detail Page)

### POST `/api/advice`

- **Authentication:** `[NoAuth]`
- **Description:** Submits a user's phone number to request advice.
- **Request Body:**
  ```json
  {
    "phone_number": "09123456789"
  }
  ```
- **Example Response (Success):**
  ```json
  {
    "message": "Our advisors will contact you shortly."
  }
  ```

### GET `/api/courses/related`

- **Authentication:** `[NoAuth]`
- **Description:** Returns courses related to a given course.
- **Query Parameters:**
  - `course_slug` (string): The slug of the current course.
- **Example Response:** (Same structure as `/api/courses/recent`)

### GET `/api/courses/{slug}`

- **Authentication:** `[NoAuth]`
- **Description:** Returns detailed information for a single course.
- **Example Response:**
  ```json
  {
    "data": {
      "slug": "advanced-laravel",
      "title": "Advanced Laravel",
      "teacher_name": "Saeid Sharafi",
      "caption": "Take your Laravel skills to the next level.",
      "main_image_link": "https://api.jedu.ir/storage/courses/advanced-laravel.jpg",
      "images_link": [
        "https://api.jedu.ir/storage/courses/gallery/1.jpg"
      ],
      "level": "Advanced",
      "rating": {
        "average": 4.8,
        "number_of_rates": 150
      },
      "length_info": {
        "hours": 40,
        "number_of_chapters": 25,
        "number_of_lessons": 200,
        "number_of_exercises": 50
      },
      "capacity_in_percent": 85,
      "register_end_date": "2025-09-30",
      "headlines": [
        "Master the Service Container",
        "Advanced Eloquent Techniques"
      ],
      "achievements": [
        "Build complex applications",
        "Contribute to open-source projects"
      ],
      "teachers": [
        {
          "slug": "saeid-sharafi",
          "name": "Saeid Sharafi",
          "image": "https://api.jedu.ir/storage/teachers/saeid-sharafi.jpg"
        }
      ],
      "jobs": [
        "Senior Backend Developer",
        "Laravel Specialist"
      ],
      "prerequisites": [
        { "slug": "laravel-basics", "name": "Laravel Basics" }
      ],
      "course_buy_methods": [
        { "id": 1, "name": "Full Payment", "price": 500000 },
        { "id": 2, "name": "Installment", "price": 550000 }
      ],
      "faqs": [
        {
          "question": "Is this course up-to-date?",
          "answer": "Yes, it's updated for the latest Laravel version."
        }
      ]
    }
  }
  ```

### GET `/api/student-stories`

- **Authentication:** `[NoAuth]`
- **Description:** Returns student stories for a specific course.
- **Query Parameters:**
  - `courseSlug` (string): The slug of the course.
- **Example Response:** (Same structure as `/api/student-stories` on the main page)

### GET `/api/teachers/{slug}`

- **Authentication:** `[NoAuth]`
- **Description:** Returns details for a specific teacher.
- **Example Response:**
  ```json
  {
    "data": {
      "slug": "saeid-sharafi",
      "name": "Saeid Sharafi",
      "caption": "Lead Instructor and Backend Engineer",
      "image": "https://api.jedu.ir/storage/teachers/saeid-sharafi.jpg",
      "linkedin": "https://linkedin.com/in/saeidsharafi",
      "rating": {
        "average": 4.9,
        "number_of_rates": 500
      },
      "number_of_courses": 15
    }
  }
  ```

### POST `/api/course/payment`

- **Authentication:** `[Auth]`
- **Description:** Initiates the payment process for a course.
- **Request Body:**
  ```json
  {
    "course_slug": "advanced-laravel",
    "classId": 5,
    "paymentType": "full"
  }
  ```
- **Example Response:**
  ```json
  {
    "data": {
      "payment_gateway_url": "https://payment.gateway/token"
    }
  }
  ```

---

## Blog

### GET `/api/blog/showcase`

- **Authentication:** `[NoAuth]`
- **Description:** Returns showcase articles for the main blog page.
- **Example Response:**
  ```json
  {
    "data": [
      {
        "slug": "laravel-11-new-features",
        "link": "/blog/laravel-11-new-features",
        "name": "What's New in Laravel 11?",
        "time_to_read": 10,
        "publish_date": "2025-09-01",
        "image": "https://api.jedu.ir/storage/blog/laravel-11.jpg",
        "rating": 4.9,
        "orientation": "horizontal"
      }
    ]
  }
  ```

### GET `/api/blog/recent-and-popular`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a paginated list of recent or popular blog posts.
- **Query Parameters:**
  - `filter` (string): `recent` or `popular`.
  - `page` (integer): The page number.
- **Example Response:** (Same structure as `/api/blog/showcase` with pagination)

### GET `/api/blog/recent`

- **Authentication:** `[NoAuth]`
- **Description:** Returns recent blog posts for a specific category.
- **Query Parameters:**
  - `categorySlug` (string): The slug of the blog category.
- **Example Response:** (Same structure as `/api/blog/showcase`)

### GET `/api/blog/related`

- **Authentication:** `[NoAuth]`
- **Description:** Returns blog posts related to a specific article or category.
- **Query Parameters:**
  - `categorySlug` (string): The slug of the blog category.
- **Example Response:** (Same structure as `/api/blog/showcase`)

### GET `/api/blog/{slug}`

- **Authentication:** `[NoAuth]`
- **Description:** Returns the full details of a single blog post.
- **Example Response:**
  ```json
  {
    "data": {
      "slug": "laravel-11-new-features",
      "title": "What's New in Laravel 11?",
      "publish_date": "2025-09-01",
      "time_to_read": 10,
      "rating": 4.9,
      "content": "<p>In this article, we explore the new features...</p>",
      "related_courses": [
        {
          "slug": "advanced-laravel",
          "name": "Advanced Laravel",
          "link": "/courses/advanced-laravel"
        }
      ]
    }
  }
  ```

### GET `/api/blog/popular`

- **Authentication:** `[NoAuth]`
- **Description:** Returns a list of popular blog posts.
- **Example Response:** (Same structure as `/api/blog/showcase`)

---

## General

### GET `/api/contact-us`

- **Authentication:** `[NoAuth]`
- **Description:** Returns contact information for the company.
- **Example Response:**
  ```json
  {
    "data": {
      "addresses": [
        {
          "name": "Main Office",
          "map_coordinates": { "lat": 35.7, "lng": 51.4 },
          "text": "Tehran, Main St, 123"
        }
      ],
      "working_hours": "Saturday to Wednesday, 9am to 5pm",
      "email": "info@jedu.ir",
      "social_media_links": [
        { "platform": "twitter", "link": "https://twitter.com/jedushop" }
      ]
    }
  }
  ```

### POST `/api/contact-us/request`

- **Authentication:** `[NoAuth]`
- **Description:** Submits a contact request form.
- **Request Body (multipart/form-data):**
  - `name` (string)
  - `number` (string)
  - `department` (string)
  - `email` (string)
  - `suggested_topics` (string)
  - `resume_form_file` (file)
- **Example Response (Success):**
  ```json
  {
    "message": "Your request has been submitted successfully."
  }
  ```

### GET `/api/collaboration-us`

- **Authentication:** `[NoAuth]`
- **Description:** Returns content related to collaboration opportunities.
- **Example Response:**
  ```json
  {
    "data": {
      "content": "<h1>Become an Instructor</h1><p>Share your knowledge with thousands of students...</p>"
    }
  }
  ```

### POST `/api/collaboration-us/request`

- **Authentication:** `[NoAuth]`
- **Description:** Submits a collaboration request form.
- **Request Body (multipart/form-data):** (Same as `/api/contact-us/request`)
- **Example Response (Success):**
  ```json
  {
    "message": "Your collaboration request has been submitted."
  }
  ```

### GET `/api/about-us`

- **Authentication:** `[NoAuth]`
- **Description:** Returns information about the company.
- **Example Response:**
  ```json
  {
    "data": {
      "about_us_text": "JeduShop is a leading online learning platform...",
      "images": [
        "https://api.jedu.ir/storage/about/office1.jpg"
      ],
      "our_skills_html": "<ul><li>Expert Instructors</li><li>Hands-on Projects</li></ul>"
    }
  }
  ```

**Response Fields:**
- `id` (integer): Order ID
- `order_number` (string): Unique order identifier
- `status` (string): Order status
- `total_amount` (integer): Total amount of the order
- `created_at` (string): Date and time of order creation
- `items` (array): List of items in the order
- `shipping_address` (object): Shipping address details

---

### POST `/api/cart/checkout` [Auth]
Initiates the checkout process for the items in the user's cart.

**Example Request:**
```bash
curl --request POST \
  "https://api.jedu.ir/api/cart/checkout" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer {token}" \
  --data '{
    "payment_method": "credit_card"
  }'
```

**Example Response:**
```json
{
  "data": {
    "order_id": 2,
    "redirect_url": "https://payment.gateway/12345"
  },
  "message": "Checkout successful, redirecting to payment gateway."
}
```

**Response Fields:**
- `order_id` (integer): The newly created order ID
- `redirect_url` (string): The URL to redirect the user for payment

---

## Order Endpoints

### GET `/api/orders/{id}/invoice` [Auth]
Returns the invoice for a specific order.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/orders/1/invoice" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
  "data": {
    "invoice_id": "INV-12345",
    "order_number": "ORD-12345",
    "amount": 150000,
    "issue_date": "2025-09-10",
    "due_date": "2025-09-25",
    "download_url": "https://api.jedu.ir/invoices/INV-12345.pdf"
  }
}
```

**Response Fields:**
- `invoice_id` (string): The invoice identifier
- `order_number` (string): The related order number
- `amount` (integer): The invoice amount
- `issue_date` (string): The date the invoice was issued
- `due_date` (string): The date the invoice is due
- `download_url` (string): A link to download the invoice PDF

---

**Notes:**
- User and Order endpoints require authentication.
- Admin coverage for these endpoints is assumed to exist but is not detailed here.


---

## Blog Endpoints

### GET `/api/blog/posts` [NoAuth]
Returns a paginated list of blog posts.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/blog/posts?page=1&per_page=10" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "How to Learn Math Fast",
      "slug": "learn-math-fast",
      "image": "https://api.jedu.ir/storage/blog/math.jpg",
      "excerpt": "Tips and tricks for learning math quickly.",
      "published_at": "2025-09-01T10:00:00+03:30"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 50
  }
}
```

**Response Fields:**
- `id` (integer): Blog post ID
- `title` (string): Title
- `slug` (string): Slug
- `image` (string): Image URL
- `excerpt` (string): Short summary
- `published_at` (string): Publish date/time

---

### GET `/api/blog/posts/{id}` [NoAuth]
Returns details for a specific blog post.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/blog/posts/1" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": {
    "id": 1,
    "title": "How to Learn Math Fast",
    "slug": "learn-math-fast",
    "image": "https://api.jedu.ir/storage/blog/math.jpg",
    "content": "Full blog post content here...",
    "published_at": "2025-09-01T10:00:00+03:30"
  }
}
```

**Response Fields:**
- `id` (integer): Blog post ID
- `title` (string): Title
- `slug` (string): Slug
- `image` (string): Image URL
- `content` (string): Full content
- `published_at` (string): Publish date/time

---

### GET `/api/blog/categories` [NoAuth]
Returns blog categories.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/blog/categories" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Education",
      "slug": "education"
    }
  ]
}
```

**Response Fields:**
- `id` (integer): Category ID
- `name` (string): Category name
- `slug` (string): Category slug

---

**Notes:**
- No Admin coverage for Blog endpoints (missing in Admin section)

---

## Contact & Collaboration Endpoints

### GET `/api/contact` [NoAuth]
Returns contact information for the site.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/contact" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": {
    "phone": "+98-21-12345678",
    "email": "info@jedu.ir",
    "address": "Tehran, Iran",
    "social_media": [
      {"platform": "Instagram", "url": "https://instagram.com/jedushop"}
    ]
  }
}
```

**Response Fields:**
- `phone` (string): Contact phone number
- `email` (string): Contact email address
- `address` (string): Physical address
- `social_media` (array): Social media links

---

### GET `/api/cooperation` [NoAuth]
Returns cooperation info (partner program).

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/cooperation" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": [
    {
      "title": "Become a Partner",
      "image": "https://api.jedu.ir/storage/cooperation/partner.jpg",
      "caption": "Join our partner program.",
      "link": "https://jedu.ir/partners"
    }
  ]
}
```

**Response Fields:**
- `title` (string): Title
- `image` (string): Image URL
- `caption` (string): Caption
- `link` (string): Link

---

### GET `/api/partners` [NoAuth]
Returns partner images.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/partners" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": [
    {
      "image_link": "https://api.jedu.ir/storage/partners/partner1.jpg"
    }
  ]
}
```

**Response Fields:**
- `image_link` (string): Partner image URL

---

**Notes:**
- No Admin coverage for Contact & Collaboration endpoints (missing in Admin section)

---

## About Us Endpoint

### GET `/api/about` [NoAuth]
Returns information about the company and team.

**Example Request:**
```bash
curl --request GET \
  "https://api.jedu.ir/api/about" \
  --header "Accept: application/json"
```

**Example Response:**
```json
{
  "data": {
    "title": "About JeduShop",
    "description": "JeduShop is an online learning platform...",
    "team": [
      {"name": "Saeid Sharafi", "role": "Founder", "image": "https://api.jedu.ir/storage/team/saeid.jpg"}
    ],
    "mission": "Empowering learners everywhere."
  }
}
```

**Response Fields:**
- `title` (string): Section title
- `description` (string): Company description
- `team` (array): Team members (name, role, image)
- `mission` (string): Mission statement

---

**Notes:**
- No Admin coverage for About Us endpoint (missing in Admin section)

---
