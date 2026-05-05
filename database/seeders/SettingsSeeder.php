<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\Admin\Settings\ContactInfoData;
use App\Data\Admin\Settings\FooterData;
use App\Data\Admin\Settings\HeaderData;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use Illuminate\Database\Seeder;

final class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Contact Info Settings
        Setting::setValue(SettingKeyEnum::CONTACT_INFO, ContactInfoData::getDefaults(), 'json', 'contact');

        Setting::setValue(SettingKeyEnum::HEADER, HeaderData::getDefaults(), 'json', 'header');

        // Footer Settings (placeholder)
        Setting::setValue(SettingKeyEnum::FOOTER, FooterData::getDefaults(), 'json', 'footer');

        // About Us Settings (placeholder)
        Setting::setValue(SettingKeyEnum::ABOUT_US, [
            'title'      => 'درباره جدویار',
            'main_block' => [
                'title'   => 'جدویار، مرکز آموزش‌های تخصصی و مهارتی',
                'content' => 'جدویار با هدف ارتقاء سطح دانش و مهارت‌های افراد در زمینه‌های مختلف، از سال ۱۳۹۰ فعالیت خود را آغاز کرده است. این مرکز با بهره‌گیری از اساتید مجرب و امکانات پیشرفته، دوره‌های آموزشی متنوعی را در حوزه‌های فنی، مهندسی، علوم انسانی، زبان‌های خارجی و هنر ارائه می‌دهد. جدویار با تاکید بر آموزش‌های کاربردی و پروژه‌محور، تلاش می‌کند تا دانشجویان را برای ورود به بازار کار آماده سازد و نقش موثری در توسعه نیروی انسانی متخصص ایفا کند.',
                'image'   => null,
            ],
            'images'                     => [],
            'active_course_groups_block' => [
                'title'   => 'گروه‌های آموزشی فعال',
                'content' => '<ol>
                                <li>فنی و مهندسی</li>
                                <li>مهندسی کامپیوتر</li>
                                <li>علوم انسانی</li>
                                <li>زبان‌های خارجه</li>
                                <li>فرهنگ و هنر</li>
                                <li>علوم پزشکی</li>
                                <li>کشاورزی</li>
                                <li>صنعت</li>
                                <li>آموزش‌های سازمانی</li>
                                <li>آموزش مجازی</li>
                                <li>آموزش‌های عالی آزاد</li>
                                <li>اشتغال و کارآفرینی</li>
                               </ol>',
                'image' => null,
            ],
            'capabilities_block' => [
                'title'   => 'قابلیت‌های جدویار',
                'content' => '<ul>
        <li>گسترده‌ترین شبکه آموزش در سطح استان</li>
        <li>برگزاری سالانه بیش از ۶۰۰ دوره عمومی و تخصصی</li>
        <li>آموزش بیش از ۲۰۰۰۰ نفر در سال</li>
        <li>اجرای دوره‌های آموزشی ویژه کارکنان دولت، شهرداری‌ها و دهیاری‌ها</li>
        <li>برخورداری از سامانه یکپارچه آموزش‌های مجازی</li>
        <li>اجرای دوره‌های غیرحضوری، آنلاین، پروژه‌محور و شغل‌محور</li>
        <li>صدور گواهینامه‌های معتبر و قابل ترجمه</li>
        <li>همکاری با دستگاه‌های اجرایی از طریق تفاهم‌نامه‌های رسمی</li>
        <li>برگزاری آزمون‌های استخدامی و ارزیابی‌های شایستگی</li>
        <li>انجام نیازسنجی مستمر و طراحی دوره‌های متناسب با بازار کار</li>
        <li>برخورداری از واحد اشتغال و کارآفرینی</li>
        <li>دارای اساتید متخصص، مجرب، توانمند، متعهد و متدین</li>
    </ul>',
                'image' => null,
            ],
            'about_online_course_block_1' => [
                'title'   => 'دوره‌های آنلاین جدویار',
                'content' => 'جدویار با ارائه دوره‌های آنلاین متنوع در زمینه‌های مختلف، امکان یادگیری از هر مکان و در هر زمان را برای شما فراهم می‌کند. با استفاده از فناوری‌های پیشرفته، تجربه‌ای بی‌نظیر از آموزش آنلاین را تجربه کنید.',
                'image'   => null,
            ],
            'about_online_course_block_2' => [
                'title'   => 'چرا دوره‌های آنلاین جدویار؟',
                'content' => '<ul>
        <li>دسترسی آسان به محتوای آموزشی از هر نقطه جهان</li>
        <li>انعطاف‌پذیری در زمان‌بندی یادگیری</li>
        <li>تعامل مستقیم با اساتید و دانشجویان از طریق انجام‌های زنده و تالارهای گفتگو</li>
        <li>محتوای آموزشی به‌روز و مطابق با نیازهای بازار کار</li>
        <li>پشتیبانی فنی و آموزشی مستمر</li>
        <li>امکان دریافت گواهینامه معتبر پس از اتمام دوره</li>
    </ul>',
                'image' => null,
            ],
        ], 'json', 'about');

        // Rules Settings (placeholder)
        Setting::setValue(SettingKeyEnum::RULES, [
            'text' => '<h1>قوانین و مقررات</h1><p>این بخش شامل قوانین و مقررات استفاده از سایت است.</p>',
        ], 'json', 'rules');

        // Sliders Settings (placeholder)
        Setting::setValue(SettingKeyEnum::SLIDERS, [], 'json', 'homepage');

        // Home Page Settings (placeholder)
        Setting::setValue(SettingKeyEnum::HOME_PAGE_BLOCKS, [
            'main_categories'          => [],
            'banners'                  => [],
            'curated_lists'            => [],
            'webinar_banner'           => null,
            'recent_courses'           => [],
            'most_participant_courses' => [],
            'roadmaps'                 => [],
        ], 'json', 'homepage');

        Setting::setValue(SettingKeyEnum::IMS, [
            'base_url'           => config('services.ims.base_url'),
            'api_key'            => config('services.ims.api_key'),
            'enabled'            => false,
            'create_studets'     => true,
            'update_studets'     => true,
            'create_enrollments' => true,
            'update_enrollments' => true,
        ], 'json', 'integrations');

        Setting::setValue(SettingKeyEnum::MOODLE, [
            'base_url'                      => config('services.moodle.base_url'),
            'token'                         => config('services.moodle.token'),
            'auth_userkey_token'            => config('services.moodle.auth_userkey_token'),
            'default_role_id'               => config('services.moodle.default_role_id'),
            'default_login_redirect_script' => config('services.moodle.default_login_redirect_script'),
            'enabled'                       => false,
            'create_studets'                => true,
            'update_studets'                => true,
            'create_enrollments'            => true,
            'update_enrollments'            => true,
        ], 'json', 'integrations');

        Setting::setValue(SettingKeyEnum::BIG_BLUE_BUTTON, [
            'enabled'                    => false,
            'base_url'                   => config('services.bbb.base_url'),
            'secret'                     => config('services.bbb.secret'),
            'api_path'                   => config('services.bbb.api_path'),
            'default_attendee_password'  => config('services.bbb.default_attendee_password'),
            'default_moderator_password' => config('services.bbb.default_moderator_password'),
        ], 'json', 'integrations');

        // 'spotplayer' => [
        //        'endpoint' => env('SPOTPLAYER_ENDPOINT', 'https://panel.spotplayer.ir/license/edit/'),
        //        'api_key'  => env('SPOTPLAYER_API_KEY'),
        //        'sandbox'  => (bool) env('SPOTPLAYER_SANDBOX', false),
        //        'timeout'  => (int) env('SPOTPLAYER_TIMEOUT', 15),
        //    ],

        Setting::setValue(SettingKeyEnum::SPOT_PLAYER, [
            'endpoint' => config('services.spotplayer.base_url'),
            'api_key'  => config('services.spotplayer.api_key'),
            'sandbox'  => config('services.spotplayer.sandbox'),
        ]);
    }
}
