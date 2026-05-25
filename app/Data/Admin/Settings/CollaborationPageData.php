<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class CollaborationPageData extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public ?MediaData $image = null,

    ) {}

    /**
     * Get default about us data for seeding.
     */
    public static function getDefaults(): array
    {
        return [
            'title'   => 'فرصت همکاری',
            'content' => <<<'HTML'
                            <h3>فرصت همکاری با موسسه آموزشی جهاد دانشگاهی استان قزوین</h3>
                            <p>
                            در جهاد دانشگاهی استان قزوین، ما باور داریم که آموزش با کیفیت، نتیجه همکاری با اساتید توانمند و پرانگیزه است. اگر شما نیز به تدریس علاقه‌مندید، تخصص خود را به اشتراک بگذارید و به خانواده آموزشی ما بپیوندید.
                            </p>
                            <h3>شرایط همکاری</h3>
                            <ul>
                            <li>داشتن تخصص علمی در یکی از حوزه‌های آموزشی مورد نیاز</li>
                            <li>سابقه تدریس یا تجربه مرتبط (ترجیحاً)</li>
                            <li>توانایی انتقال مؤثر مفاهیم و ارتباط مناسب با دانشجویان</li>
                            <li>تعهد به ارتقاء مستمر کیفیت آموزشی</li>
                            </ul>
                            <h3>مزایای همکاری با ما</h3>
                             <ul>
                            <li>دستمزد رقابتی و منظم</li>
                            <li>امکان تدریس حضوری و مجازی</li>
                            <li>همکاری با تیمی پویا و حرفه‌ای</li>
                            <li>امکان اخذ گواهی تدریس بعد از چندین دوره</li>
                            <li>قرارداد رسمی با اساتید</li>
                            
                            </ul>
                            <p>
                            اگر علاقه‌مند به همکاری هستید، لطفاً فرم زیر را تکمیل کنید و رزومه خود را بارگزاری نمایید.
                            </p>
                            HTML
        ];
    }
}
