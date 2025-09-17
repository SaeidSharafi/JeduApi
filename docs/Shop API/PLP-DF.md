# API Documentation: PLP - Books & Pamphlets (Digital Assets)

This document outlines the public API endpoints required to render the listing page for digital assets like books and pamphlets. The structure closely follows the patterns established for other product listing pages. All endpoints are prefixed with `/api/v1/`.

---

## 1. Page Content

This endpoint provides the static content for the page's header and banner.

### GET `/api/v1/pages/digital-assets`

Retrieves the main content for the Digital Assets listing page.

-   **Authentication:** `NoAuth`
-   **Controller:** `app/Http/Controllers/Api/Shop/PageContentController.php` (Existing)
-   **Response DTO:** `PageContentData`
-   **Backend Logic:** Fetches content from a `Page` model or a similar key-value store using the key `digital-assets`.

**Response Body:**

```json
{
  "data": {
    "title": "کتاب ها و جزوات",
    "description": "رایانه‌ها نیروی محرکه پیشرفت و سرعت بخشیدن به امور کاری در هر زمینه‌ای هستند. برای مطالعه و به‌کارگیری علوم کامپیوتر، کنترل تعامل بین توسعه نرم افزار و سخت افزار کامپیوتر و الگوریتم هایی که آنها را اجرا می کنند...",
    "image_url": "https://api.jedu.ir/media/pages/digital_assets_banner.png"
  }
}
```

## 2. Digital Asset Listing, Filtering & Searching

The main products endpoint is used to fetch, filter, search, and sort all digital assets on the page, including the main grid and the "Bestsellers" carousel.

### GET /api/v1/products

Retrieves a paginated list of products, specifically filtered to show only DigitalAsset types.

-   **Authentication:**  NoAuth

-   **Controller:**  app/Http/Controllers/Api/Shop/ProductController.php (Existing)

-   **Response DTO:**  ProductCardData Collection (Paginated)


**Mandatory Query Parameter:**

-   filter[productable_type] (string, required): Must be set to digital_asset.


**Other Key Query Parameters:**

-   filter[name] (string, optional): The search query from the search bar (e.g., "ICDL").

-   filter[category_slug] (string, optional): The slug of the currently selected category (e.g., computer-science).

-   sort (string, optional): The sorting criteria.

    -   -enrolment_count: For the "پرفروش ترین ها" (Bestsellers) section.

    -   -created_at: Can be used as a default sort order for the main grid.

-   page (integer, optional): The page number for pagination in the main grid.

-   limit (integer, optional): Number of items per page.


**Response Fields (ProductCardData) are the same as other PLPs.**

**Example Usage:**

1.  **For the Main Grid (Default view for "Computer Science"):**  
    GET /api/v1/products?filter[productable_type]=digital_asset&filter[category_slug]=computer-science

2.  **After a User Searches:**  
    GET /api/v1/products?filter[productable_type]=digital_asset&filter[name]=ICDL

3.  **For the "پرفروش ترین ها" (Bestsellers) Carousel:**  
    GET /api/v1/products?filter[productable_type]=digital_asset&sort=-enrolment_count&limit=8


**Example Paginated Response for the Main Grid:**

```json
{
  "data": [
    {
      "uuid": "da_def456",
      "name": "کتاب ICDL مقدماتی",
      "product_type": "DigitalAsset",
      "teacher_name": "محسن مردانی",
      "cover_image_url": "https://api.jedu.ir/media/products/book_cover_1.jpg",
      "average_rating": 4.5,
      "tags": ["گواهی معتبر", "محبوب فراگیران"],
      "status": "فعال",
      "price": 2999000,
      "original_price": null,
      "discount_percentage": null
    }
  ],
  "links": {
    "first": "https://api.jedu.ir/v1/products?page=1",
    "last": "https://api.jedu.ir/v1/products?page=4",
    "prev": null,
    "next": "https://api.jedu.ir/v1/products?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "path": "https://api.jedu.ir/v1/products",
    "per_page": 9,
    "to": 9,
    "total": 36
  }
}
```

## 3. Reusable Endpoints

The following endpoints, already defined for other pages, should be reused here to maintain consistency and reduce development effort.

### Category Filters

To populate the filter chips below the search bar.

-   **Endpoint:**  GET /api/v1/categories/main-page


### Footer

The standard site footer is identical on this page.

-   **Endpoint:**  GET /api/v1/settings/footer
