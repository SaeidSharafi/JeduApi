<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Site;

use App\Data\Shop\Site\HeaderData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;

/**
 * @group Shop - Shared
 *
 * API for retrieving site configuration
 */
final class HeaderController extends Controller
{
    /**
     * Get Header Configuration
     *
     * Returns the configuration settings for the website header.
     *
     * @response 200 {
     *  "message": "عملیات با موفقیت انجام شد.",
     *  "data": {
     *      "logo_url": null,
     *      "navigation_links": [
     *          {
     *              "title": "درباره ما",
     *              "url": "/about-us"
     *          },
     *          {
     *              "title": "ارتباط با ما",
     *              "url": "/contact-us"
     *          },
     *          {
     *              "title": "کتب و جزوات",
     *              "url": "/books"
     *          },
     *          {
     *              "title": "وبینارها",
     *              "url": "/webinars"
     *          },
     *          {
     *              "title": "مدرک بین المللی",
     *              "url": "/international-certificate"
     *          },
     *          {
     *              "title": "استعلام مدرک",
     *              "url": "/certificate-verification"
     *          },
     *          {
     *              "title": "بلاگ",
     *              "url": "/blog"
     *          },
     *          {
     *              "title": "دوره‌ها",
     *              "url": "/courses"
     *          }
     *      ],
     *      "contact_phone": "+98-21-12345678",
     *      "contact_email": "info@jedu.ir"
     *  },
     *  "metadata": []
     * }
     * */
    public function __invoke(SettingsService $service)
    {
        $header = $service->get('header', \App\Data\Admin\Settings\HeaderData::class::getDefaults());

        return response()->success(HeaderData::from($header));
    }
}
