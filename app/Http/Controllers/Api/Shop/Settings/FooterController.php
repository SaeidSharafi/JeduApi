<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FooterData as AdminFooterData;
use App\Data\Shop\Site\FooterData;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - Settings
 *
 * API for retrieving site configuration
 */
final class FooterController extends Controller
{
    /**
     * Get Footer Configuration
     *
     * Returns the configuration settings for the website footer.
     *
     * @response 200 {
     * "message": "عملیات با موفقیت انجام شد.",
     * "data": {
     * "logo_url": null,
     * "logo_alt": null,
     * "caption": "شریک شما در آموزش مدرن",
     * "support_link": "/contact-us",
     * "support_email_address": "support@jedu.ir",
     * "addresses": [
     * {
     * "name": "دفتر مرکزی",
     * "address": "تهران، خیابان آزادی، پلاک ۱۲۳",
     * "location_url": "https://maps.example.com/?q=35.6892,51.3890",
     * "phone": "۰۲۱-۱۲۳۴۵۶۷۸"
     * }
     * ],
     * "categories": [
     * "دوره‌ها",
     * "معماری",
     * "آموزش صنعتی",
     * "زبان‌های خارجی"
     * ],
     * "main_links": [
     * {
     * "title": "درباره ما",
     * "link": "/about-us"
     * },
     * {
     * "title": "وبلاگ",
     * "link": "/blog"
     * },
     * {
     * "title": "تماس با ما",
     * "link": "/contact-us"
     * },
     * {
     * "title": "قوانین",
     * "link": "/rules"
     * }
     * ],
     * "social_media_links": [
     * {
     * "platform": "instagram",
     * "link": "https://instagram.com/jedushop"
     * },
     * {
     * "platform": "linkedin",
     * "link": "https://linkedin.com/company/jedushop"
     * }
     * ],
     * "certifications": [
     * {
     * "name": "اینماد",
     * "image": null
     * },
     * {
     * "name": "ساماندهی",
     * "image": null
     * }
     * ]
     * },
     * "metadata": []
     * }
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $footer = $service->get('footer', AdminFooterData::getDefaults());

        return response()->success(FooterData::from($footer));
    }
}
