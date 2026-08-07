<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Data\Shop\Student\Blocks\DigitalAssetFileData;
use App\Data\Shop\Student\Enrollment\DeliveryAccessData;
use App\Data\Shop\Student\Enrollment\EnrollmentCertificateInfoData;
use App\Data\Shop\Student\Enrollment\EnrollmentDetailData;
use App\Data\Shop\Student\Enrollment\EnrollmentQuizData;
use App\Data\Shop\Student\Enrollment\EnrollmentReviewInfoData;
use App\Data\Shop\Student\Enrollment\EnrollmentSurveyBlockData;
use App\Data\Shop\Teacher\TeacherDetailData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\User\GenderEnum;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Review;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\DataCollection;

final readonly class GetEnrollmentDetailAction
{
    public function handle(Enrollment $enrollment): EnrollmentDetailData
    {
        $enrollment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem',
            'customer',
        ]);

        $deliveryOption = $enrollment->productDeliveryOption;
        $product        = ProductDeliveryOptionCardData::fromModel($deliveryOption);
        $teachers       = $this->buildTeachers($enrollment);
        $reviewInfo     = $this->buildReviewInfo($enrollment);
        $certInfo       = $this->buildCertificateInfo($enrollment);
        $surveyBlock    = $this->buildSurveyBlock($enrollment);
        $files          = $this->buildFiles($enrollment);
        $quizzes        = $this->buildQuizzes($enrollment);
        $deliveryAccess = $this->buildDeliveryAccess($enrollment);

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
            files: $files,
            quizzes: $quizzes,
            delivery_access: $deliveryAccess,
            review_info: $reviewInfo,
            certificate_info: $certInfo,
            survey_block: $surveyBlock,
        );
    }

    /**
     * @return DataCollection<int, TeacherDetailData>
     */
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
            ->where('status', PublicationStatusEnum::PUBLISHED)
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

    private function buildCertificateInfo(Enrollment $enrollment): EnrollmentCertificateInfoData
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

    private function buildSurveyBlock(Enrollment $enrollment): EnrollmentSurveyBlockData
    {
        // TODO: integrate with survey provider (Rouyesh or similar) to supply url and message
        return new EnrollmentSurveyBlockData(
            url: null,
            message: null,
        );
    }

    /**
     * @return DataCollection<int, DigitalAssetFileData>
     */
    private function buildFiles(Enrollment $enrollment): DataCollection
    {
        $productable = $enrollment->productDeliveryOption->product->productable;

        if ($productable instanceof DigitalAsset) {
            $files = collect([$productable]);
        } elseif (method_exists($productable, 'digitalAssets')) {
            $productable->loadMissing('digitalAssets');
            $files = $productable->digitalAssets ?? collect();
        } else {
            $files = collect();
        }

        $items = $files->map(fn ($asset): DigitalAssetFileData => new DigitalAssetFileData(
            id: $asset->id,
            short_name: $asset->short_name,
            full_name: $asset->full_name,
            thumbnail_url: $asset->thumbnail_url,
            download_url: route(
                'api.v1.shop.student.digital-assets.download',
                ['enrollment' => $enrollment->uuid, 'digitalAsset' => $asset->id],
                absolute: true
            ),
        ))->values()->all();

        return new DataCollection(DigitalAssetFileData::class, $items);
    }

    /**
     * @return DataCollection<int, EnrollmentQuizData>
     */
    private function buildQuizzes(Enrollment $enrollment): DataCollection
    {
        $provisioning = $enrollment->provisioning_data['providers'] ?? [];

        $activities = data_get($provisioning, 'moodle.sync.activities')
            ?? data_get($provisioning, 'moodle_quiz.sync.activities')
            ?? [];

        // Backwards compat: old format stored activities inside course_info
        if (empty($activities)) {
            $activities = data_get($provisioning, 'moodle.data.course_info.activities', []);
        }

        $items = collect($activities)
            ->map(fn (array|object $activity): EnrollmentQuizData => new EnrollmentQuizData(
                cmid: (int) data_get($activity, 'cmid', 0),
                name: (string) data_get($activity, 'name', ''),
                type: (string) data_get($activity, 'type', ''),
                url: (string) data_get($activity, 'url', ''),
                state: (int) data_get($activity, 'state', 0),
                score: data_get($activity, 'score') ?? data_get($activity, 'grade'),
                timecompleted: data_get($activity, 'timecompleted') !== null
                    ? (int) data_get($activity, 'timecompleted')
                    : null,
            ))
            ->values()->all();

        return new DataCollection(EnrollmentQuizData::class, $items);
    }

    private function buildDeliveryAccess(Enrollment $enrollment): DeliveryAccessData
    {
        $deliveryOption = $enrollment->productDeliveryOption;
        $deliveryMethod = $deliveryOption->delivery_method;
        $details        = $deliveryOption->details_json               ?? [];
        $provisioning   = $enrollment->provisioning_data['providers'] ?? [];

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_BBB,
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM => new DeliveryAccessData(
                type: $deliveryMethod->value,
                session_label: 'کلاس آنلاین',
                join_url_path: '/api/v1/shop/my-courses/'.$enrollment->uuid.'/join',
            ),
            DeliveryMethodEnum::LMS_MOODLE => new DeliveryAccessData(
                type: $deliveryMethod->value,
                course_url: data_get($provisioning, 'moodle.data.course_url'),
                completed: (bool) (
                    data_get($provisioning, 'moodle.sync.completed')
                    ?? data_get($provisioning, 'moodle.data.course_info.completed', false)
                ),
                course_grade: data_get($provisioning, 'moodle.sync.course_grade')
                    ?? data_get($provisioning, 'moodle.data.course_info.course_grade'),
            ),
            DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER => new DeliveryAccessData(
                type: $deliveryMethod->value,
                license_key: data_get($provisioning, 'spotplayer.data.license_key'),
                player_url: data_get($provisioning, 'spotplayer.data.player_url'),
            ),
            DeliveryMethodEnum::IN_PERSON => new DeliveryAccessData(
                type: $deliveryMethod->value,
                address: data_get($details, 'address'),
                map_url: data_get($details, 'map_url'),
            ),
            DeliveryMethodEnum::DIRECT_DOWNLOAD => new DeliveryAccessData(
                type: $deliveryMethod->value,
            ),
        };
    }
}
