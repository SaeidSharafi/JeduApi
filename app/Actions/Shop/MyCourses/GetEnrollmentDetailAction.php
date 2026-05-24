<?php

declare(strict_types=1);

namespace App\Actions\Shop\MyCourses;

use App\Data\Shop\MyCourses\Blocks\DigitalAssetBlockData;
use App\Data\Shop\MyCourses\Blocks\DigitalAssetFileData;
use App\Data\Shop\MyCourses\Blocks\InPersonBlockData;
use App\Data\Shop\MyCourses\Blocks\LiveSessionBbbBlockData;
use App\Data\Shop\MyCourses\Blocks\LiveSessionSkyroomBlockData;
use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Data\Shop\MyCourses\Blocks\VideoPlatformSpotplayerBlockData;
use App\Data\Shop\MyCourses\EnrollmentCertificateInfoData;
use App\Data\Shop\MyCourses\EnrollmentDetailData;
use App\Data\Shop\MyCourses\EnrollmentReviewInfoData;
use App\Data\Shop\MyCourses\EnrollmentSurveyBlockData;
use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Data\Shop\Teacher\TeacherDetailData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\User\GenderEnum;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Review;
use App\Services\Integrations\BbbService;
use App\Services\SettingsService;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\DataCollection;
use Throwable;

final readonly class GetEnrollmentDetailAction
{
    public function __construct(private BbbService $bbbService, private SettingsService $settings) {}

    public function handle(Enrollment $enrollment): EnrollmentDetailData
    {
        $enrollment->loadMissing([
            'productDeliveryOption.product.productable',
            'productDeliveryOption.teachers.media',
            'orderItem',
            'customer',
        ]);

        $deliveryOption  = $enrollment->productDeliveryOption;
        $product         = ProductDeliveryOptionCardData::fromModel($deliveryOption);
        $teachers        = $this->buildTeachers($enrollment);
        $reviewInfo      = $this->buildReviewInfo($enrollment);
        $certificateInfo = $this->buildCertificateInfo($enrollment);
        $surveyBlock     = $this->buildSurveyBlock($enrollment);
        $deliveryBlock   = $this->buildDeliveryBlock($enrollment);

        return new EnrollmentDetailData(
            uuid: $enrollment->uuid,
            enrollment_status: $enrollment->enrollment_status,
            access_start_date: $enrollment->access_start_date
                ? Verta::instance($enrollment->access_start_date)
                : null,
            access_end_date: $enrollment->access_end_date
                ? Verta::instance($enrollment->access_end_date)
                : null,
            external_enrollment_id: $enrollment->external_enrollment_id !== null
                ? (string) $enrollment->external_enrollment_id : null,
            notes: $enrollment->notes,
            product: $product,
            teachers: $teachers,
            review_info: $reviewInfo,
            certificate_info: $certificateInfo,
            survey_block: $surveyBlock,
            delivery_block: $deliveryBlock,
        );
    }

    private function buildTeachers(Enrollment $enrollment): DataCollection
    {
        $teachers = $enrollment->productDeliveryOption->teachers ?? collect();

        $items = $teachers->map(fn ($teacher): TeacherDetailData => new TeacherDetailData(
            uuid: $teacher->uuid,
            first_name: $teacher->first_name,
            last_name: $teacher->last_name,
            bio: $teacher->bio               ?? '',
            avatar_url: $teacher->avatar_url ?? '',
            rate: (float) ($teacher->rate ?? 0),
            gender: $teacher->gender ?? GenderEnum::MALE,
            social_links: $teacher->social_links,
        ))->values()->all();

        return new DataCollection(TeacherDetailData::class, $items);
    }

    private function buildReviewInfo(Enrollment $enrollment): EnrollmentReviewInfoData
    {
        $productable = $enrollment->productDeliveryOption->product->productable;

        $review = Review::query()
            ->where('user_id', $enrollment->customer_id)
            ->where('reviewable_type', $productable::class)
            ->where('reviewable_id', $productable->id)
            ->first();

        return new EnrollmentReviewInfoData(
            has_reviewed: $review !== null,
            review: $review
                ? [
                    'id'      => $review->id,
                    'rating'  => $review->rating,
                    'title'   => $review->title,
                    'comment' => $review->comment,
                    'status'  => $review->status,
                ]
                : null,
        );
    }

    private function buildCertificateInfo(Enrollment $enrollment): ?EnrollmentCertificateInfoData
    {
        $productable = $enrollment->productDeliveryOption->product->productable;

        $providesCertificate = (bool) ($productable->provides_certificate ?? false);

        if (! $providesCertificate) {
            return new EnrollmentCertificateInfoData(
                is_available: false,
                certificate_url: null, // TODO: generate certificate URL when certificate generation is implemented
            );
        }

        $isAvailable = $enrollment->survey_completed_at !== null;

        return new EnrollmentCertificateInfoData(
            is_available: $isAvailable,
            certificate_url: null, // TODO: generate certificate URL when certificate generation is implemented
        );
    }

    private function buildSurveyBlock(Enrollment $enrollment): ?EnrollmentSurveyBlockData
    {
        // TODO: integrate with survey provider (Rouyesh or similar) to supply url and message
        return new EnrollmentSurveyBlockData(
            url: null,
            message: null,
        );
    }

    private function buildDeliveryBlock(
        Enrollment $enrollment
    ): LiveSessionBbbBlockData|LiveSessionSkyroomBlockData|LmsMoodleBlockData|VideoPlatformSpotplayerBlockData|InPersonBlockData|DigitalAssetBlockData|null {
        $deliveryOption = $enrollment->productDeliveryOption;
        $deliveryMethod = $deliveryOption->delivery_method;
        $details        = $deliveryOption->details_json               ?? [];
        $provisioning   = $enrollment->provisioning_data['providers'] ?? [];

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_BBB          => $this->buildBbbBlock($enrollment, $details, $provisioning),
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM      => $this->buildSkyroomBlock(),
            DeliveryMethodEnum::LMS_MOODLE                => $this->buildMoodleBlock($provisioning),
            DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER => $this->buildSpotplayerBlock($provisioning),
            DeliveryMethodEnum::IN_PERSON                 => $this->buildInPersonBlock($details),
            DeliveryMethodEnum::DIRECT_DOWNLOAD           => $this->buildDirectDownloadBlock($enrollment),
        };
    }

    private function buildBbbBlock(Enrollment $enrollment, array $details, array $provisioning): LiveSessionBbbBlockData
    {
        $meetingId = data_get($details, 'meeting_id');
        $joinUrl   = null;

        if (is_string($meetingId) && $meetingId !== '') {
            try {
                $bbbConfig = $this->settings->get(\App\Enums\System\SettingKeyEnum::BIG_BLUE_BUTTON);

                if (($bbbConfig['enabled'] ?? false) && ! empty($bbbConfig['base_url'])
                                                     && ! empty($bbbConfig['secret'])
                ) {
                    $this->bbbService->setConfig($bbbConfig);

                    $customer = $enrollment->customer;
                    $fullName = mb_trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Student';

                    $attendeePassword = data_get($details, 'attendee_password');
                    $joinUrl          = $this->bbbService->buildJoinUrl($meetingId, $fullName, $attendeePassword);
                }
            } catch (Throwable) {
                // BBB config missing or service unavailable; join_url stays null
            }
        }

        $startDate = data_get($details, 'start_date');

        // TODO: fetch BBB recordings via BBB API (getRecordings) and populate past_recordings
        return new LiveSessionBbbBlockData(
            join_url: $joinUrl,
            start_date: is_string($startDate) ? $startDate : null,
            past_recordings: [],
        );
    }

    private function buildSkyroomBlock(): LiveSessionSkyroomBlockData
    {
        // TODO: integrate with Skyroom API to build join_url and fetch recordings
        return new LiveSessionSkyroomBlockData(
            join_url: null,
            start_date: null,
            past_recordings: [],
        );
    }

    private function buildMoodleBlock(array $provisioning): ?LmsMoodleBlockData
    {
        $moodleData = $provisioning['moodle']['data'] ?? [];
        $courseInfo = data_get($moodleData, 'course_info');

        if (! is_array($courseInfo) || empty($courseInfo)) {
            return null;
        }

        return LmsMoodleBlockData::from($courseInfo);
    }

    private function buildSpotplayerBlock(array $provisioning): VideoPlatformSpotplayerBlockData
    {
        $spotData   = $provisioning['spotplayer']['data'] ?? [];
        $licenseKey = data_get($spotData, 'license_key');
        $playerUrl  = data_get($spotData, 'player_url');

        return new VideoPlatformSpotplayerBlockData(
            license_key: is_string($licenseKey) ? $licenseKey : null,
            player_url: is_string($playerUrl) ? $playerUrl : null,
        );
    }

    private function buildInPersonBlock(array $details): InPersonBlockData
    {
        $location = data_get($details, 'address');
        $mapUrl   = data_get($details, 'map_url') ?? data_get($details, 'additional_info');

        return new InPersonBlockData(
            address: is_string($location) ? $location : null,
            map_url: is_string($mapUrl) ? $mapUrl : null,
        );
    }

    private function buildDirectDownloadBlock(Enrollment $enrollment): DigitalAssetBlockData
    {
        $productable = $enrollment->productDeliveryOption->product->productable;

        $assets = collect();

        if ($productable instanceof DigitalAsset) {
            $assets->push($productable);
        } elseif (method_exists($productable, 'digitalAssets')) {
            $productable->loadMissing('digitalAssets');
            $assets = $productable->digitalAssets;
        }

        // Deduplicate by id
        $assets = $assets->unique('id')->values();

        $files = $assets->map(fn (DigitalAsset $asset): DigitalAssetFileData => new DigitalAssetFileData(
            id: $asset->id,
            short_name: $asset->short_name,
            full_name: $asset->full_name,
            thumbnail_url: $asset->thumbnail_url,
            download_url: route('api.v1.shop.my-digital-assets.download',
                ['enrollment' => $enrollment->uuid, 'digitalAsset' => $asset->id], absolute: true),
        ))->values()->all();

        return new DigitalAssetBlockData(
            files: new DataCollection(DigitalAssetFileData::class, $files),
        );
    }
}
