<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class EnrollmentData extends Data
{
    // #[Computed]
    // public ProductData $product;

    public function __construct(
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public EnrollmentStatusEnum $enrollment_status,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_end_date,
        public ?string $notes,
        public ?array $provisioning_data,
        public ?string $survey_completed_at,
        public ProductDeliveryOption $productDeliveryOption,
        public OrderItem $orderItem,
        public bool $is_virtual = false,
        public ?ProductDeliveryOptionCardData $product = null,
    ) {
        $this->product    = ProductDeliveryOptionCardData::fromModel($productDeliveryOption);
        $this->is_virtual = $productDeliveryOption->delivery_method->isVirtual();

        $productableType  = $productDeliveryOption->product?->productable_type;
        $isSurveyEligible = in_array($productableType, [
            ProductableEnum::COURSE->value,
            ProductableEnum::SEMINAR->value,
        ], true);
        if ($isSurveyEligible && $productDeliveryOption->survey_required && $this->survey_completed_at === null) {
            $this->provisioning_data = $this->withoutCourseResults($this->provisioning_data);
        }
    }

    protected function exceptProperties(): array
    {
        return [
            'productDeliveryOption',
            'orderItem',
            'survey_completed_at',
        ];
    }

    /** @param array<string, mixed>|null $provisioningData
     * @return array<string, mixed>|null
     */
    private function withoutCourseResults(?array $provisioningData): ?array
    {
        foreach (['moodle', 'moodle_quiz'] as $provider) {
            if (! isset($provisioningData['providers'][$provider]['sync'])) {
                continue;
            }

            $provisioningData['providers'][$provider]['sync']['course_grade'] = null;
            foreach ($provisioningData['providers'][$provider]['sync']['activities'] ?? [] as $index => $activity) {
                if (array_key_exists('score', $activity)) {
                    $provisioningData['providers'][$provider]['sync']['activities'][$index]['score'] = null;
                }
                if (array_key_exists('grade', $activity)) {
                    $provisioningData['providers'][$provider]['sync']['activities'][$index]['grade'] = null;
                }
            }
        }

        return $provisioningData;
    }
}
