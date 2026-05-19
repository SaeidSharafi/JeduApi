<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses;

use App\Data\Shop\MyCourses\Blocks\DigitalAssetBlockData;
use App\Data\Shop\MyCourses\Blocks\InPersonBlockData;
use App\Data\Shop\MyCourses\Blocks\LiveSessionBbbBlockData;
use App\Data\Shop\MyCourses\Blocks\LiveSessionSkyroomBlockData;
use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Data\Shop\MyCourses\Blocks\VideoPlatformSpotplayerBlockData;
use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Data\Shop\Teacher\TeacherDetailData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class EnrollmentDetailData extends Data
{
    public function __construct(
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public EnrollmentStatusEnum $enrollment_status,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_end_date,
        public ?string $external_enrollment_id,
        public ?string $notes,
        public ProductDeliveryOptionCardData $product,
        #[DataCollectionOf(TeacherDetailData::class)]
        public DataCollection $teachers,
        public EnrollmentReviewInfoData $review_info,
        public ?EnrollmentCertificateInfoData $certificate_info,
        public ?EnrollmentSurveyBlockData $survey_block,
        public LiveSessionBbbBlockData|LiveSessionSkyroomBlockData|LmsMoodleBlockData|VideoPlatformSpotplayerBlockData|InPersonBlockData|DigitalAssetBlockData|null $delivery_block,
    ) {}
}
