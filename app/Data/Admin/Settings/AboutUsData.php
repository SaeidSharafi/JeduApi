<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\ArticleSectionCreateData;
use App\Data\ArticleSectionData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AboutUsData extends Data
{
    public function __construct(
        public string $title,
        public ArticleSectionData $main_block,
        public array $images,
        public ArticleSectionData $active_course_groups_block,
        public ArticleSectionData $capabilities_block,
        public ArticleSectionData $about_online_course_block_1,
        public ArticleSectionData $about_online_course_block_2,
    ) {
    }


    /**
     * Get default about us data for seeding.
     */
    public static function getDefaults(): array
    {
        return [
            'title'                       => 'درباره جدویار',
            'main_block'                  => [
                'title'   => 'جدویار، مرکز آموزش‌های تخصصی و مهارتی',
                'content' => 'جدویار با هدف ارتقاء سطح دانش و مهارت‌های افراد در زمینه‌های مختلف، از سال ۱۳۹۰ فعالیت خود را آغاز کرده است. این مرکز با بهره‌گیری از اساتید مجرب و امکانات پیشرفته، دوره‌های آموزشی متنوعی را در حوزه‌های فنی، مهندسی، علوم انسانی، زبان‌های خارجی و هنر ارائه می‌دهد. جدویار با تاکید بر آموزش‌های کاربردی و پروژه‌محور، تلاش می‌کند تا دانشجویان را برای ورود به بازار کار آماده سازد و نقش موثری در توسعه نیروی انسانی متخصص ایفا کند.',
                'icon'    => null,
            ],
            'images'                      => [],
            'active_course_groups_block'  => [
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
                'icon'    => null,
            ],
            'capabilities_block'          => [
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
                'icon'    => null,
            ],
            'about_online_course_block_1' => [
                'title'   => 'دوره‌های آنلاین جدویار',
                'content' => 'جدویار با ارائه دوره‌های آنلاین متنوع در زمینه‌های مختلف، امکان یادگیری از هر مکان و در هر زمان را برای شما فراهم می‌کند. با استفاده از فناوری‌های پیشرفته، تجربه‌ای بی‌نظیر از آموزش آنلاین را تجربه کنید.',
                'icon'    => null,
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
                'icon'    => null,
            ],
        ];
    }
}
