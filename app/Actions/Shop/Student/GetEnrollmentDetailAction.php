<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Contracts\Integrations\ImsClientContract;
use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Data\Shop\Student\Blocks\DigitalAssetFileData;
use App\Data\Shop\Student\Enrollment\DeliveryAccessData;
use App\Data\Shop\Student\Enrollment\EnrollmentCertificateInfoData;
use App\Data\Shop\Student\Enrollment\EnrollmentDetailData;
use App\Data\Shop\Student\Enrollment\EnrollmentGradeInfoData;
use App\Data\Shop\Student\Enrollment\EnrollmentGradeReportData;
use App\Data\Shop\Student\Enrollment\EnrollmentGradeReportItemData;
use App\Data\Shop\Student\Enrollment\EnrollmentQuizData;
use App\Data\Shop\Student\Enrollment\EnrollmentReviewInfoData;
use App\Data\Shop\Student\Enrollment\EnrollmentSurveyBlockData;
use App\Data\Shop\Student\Review\ReviewData;
use App\Data\Shop\Teacher\TeacherDetailData;
use App\Enums\Content\ReviewStatusEnum;
use App\Enums\EnrollmentGradeStateEnum;
use App\Enums\EnrollmentSurveyStateEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\ProvisioningOutcomeStatusEnum;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\GenderEnum;
use App\Exceptions\BundleStructuralInvariantException;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Review;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Client\ConnectionException;
use Spatie\LaravelData\DataCollection;

final readonly class GetEnrollmentDetailAction
{
    public function __construct(
        private GenerateEnrollmentSurveyUrlAction $surveyUrl,
        private EnrollmentSurveyPolicyAction $surveyPolicy,
        private ImsClientContract $ims,
    ) {}

    public function handle(Enrollment $enrollment): EnrollmentDetailData
    {
        $enrollment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem',
            'customer',
        ]);

        if ($enrollment->productDeliveryOption->product?->productable_type === ProductableEnum::BUNDLE->value) {
            throw new BundleStructuralInvariantException();
        }

        $deliveryOption = $enrollment->productDeliveryOption;
        $surveyBlock    = $this->buildSurveyBlock($enrollment);
        $resultsLocked  = $surveyBlock->results_locked;
        $product        = ProductDeliveryOptionCardData::fromModel($deliveryOption);
        $teachers       = $this->buildTeachers($enrollment);
        $reviewInfo     = $this->buildReviewInfo($enrollment);
        $certInfo       = $this->buildCertificateInfo($enrollment, $resultsLocked);
        $files          = $this->buildFiles($enrollment);
        $quizzes        = $this->buildQuizzes($enrollment, $resultsLocked);
        $deliveryAccess = $this->buildDeliveryAccess($enrollment, $resultsLocked);
        $gradeInfo      = $this->buildGradeInfo($enrollment, $resultsLocked);

        return new EnrollmentDetailData(
            uuid: $enrollment->uuid,
            enrollment_status: $enrollment->enrollment_status,
            access_start_date: $enrollment->access_start_date
                ? Verta::instance($enrollment->access_start_date->toDateTime())
                : null,
            access_end_date: $enrollment->access_end_date
                ? Verta::instance($enrollment->access_end_date->toDateTime())
                : null,
            notes: $enrollment->notes,
            product: $product,
            teachers: $teachers,
            files: $files,
            quizzes: $quizzes,
            delivery_access: $deliveryAccess,
            review_info: $reviewInfo,
            certificate_info: $certInfo,
            survey_block: $surveyBlock,
            grade_info: $gradeInfo,
        );
    }

    private function buildGradeInfo(Enrollment $enrollment, bool $resultsLocked): EnrollmentGradeInfoData
    {
        $details          = $enrollment->productDeliveryOption->details_json ?? [];
        $courseCode       = data_get($details, 'ims_course_code');
        $provisioning     = data_get($enrollment->provisioning_data, 'providers.ims.data', []);
        $imsEnrollmentId  = data_get($provisioning, 'ims_enrollment_id');
        $hasImsCourse     = is_string($courseCode)   && $courseCode !== '';
        $hasImsEnrollment = is_int($imsEnrollmentId) && $imsEnrollmentId > 0;

        if (! $hasImsCourse && ! $hasImsEnrollment) {
            return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::NOT_SUPPORTED, null);
        }

        if ($resultsLocked || ! $this->surveyPolicy->allowsSurveyCompletion($enrollment)) {
            return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::LOCKED, null);
        }

        $customer = $enrollment->customer;

        if (! $hasImsEnrollment || ! filled($customer?->civil_id)
                                || ! ($customer?->civil_id_type instanceof CivilIdTypeEnum)
                                || ! $this->ims->isReady()
        ) {
            return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::UNAVAILABLE, null);
        }

        try {
            $response = $this->ims->getEnrollmentGradeReport(
                $imsEnrollmentId,
                $customer->civil_id,
                $customer->civil_id_type,
            );
        } catch (ExternalProvisioningException|ConnectionException) {
            return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::UNAVAILABLE, null);
        }

        $report = $this->parseGradeReport($response);

        if ($report === null) {
            return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::UNAVAILABLE, null);
        }

        if ($report->status === 'none') {
            return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::PENDING, null);
        }

        return new EnrollmentGradeInfoData(EnrollmentGradeStateEnum::AVAILABLE, $report);
    }

    /** @param array<string, mixed> $response */
    private function parseGradeReport(array $response): ?EnrollmentGradeReportData
    {
        $payload          = data_get($response, 'data');
        $status           = data_get($payload, 'status');
        $translatedStatus = data_get($payload, 'status_translated');
        $items            = data_get($payload, 'items');

        if (! is_array($payload)
            || ! in_array($status, ['passed', 'rejected', 'none', 'incomplete'], true)
            || ! is_string($translatedStatus)
            || ! is_array($items)
            || ! array_is_list($items)
        ) {
            return null;
        }

        $reportItems = [];

        foreach ($items as $item) {
            if (! is_array($item)
                || ! is_string($item['name'] ?? null)
                || ! is_string($item['label'] ?? null)
                || ! array_key_exists('value', $item)
                || ! array_key_exists('pass_grade', $item)
                || ! array_key_exists('is_passed', $item)
            ) {
                return null;
            }

            $value     = $item['value'];
            $passGrade = $item['pass_grade'];
            $isPassed  = $item['is_passed'];

            if (($value !== null && ! is_int($value) && ! is_float($value))
                || ($passGrade !== null && ! is_int($passGrade) && ! is_float($passGrade))
                || ($isPassed !== null && ! is_bool($isPassed))
            ) {
                return null;
            }

            $reportItems[] = new EnrollmentGradeReportItemData(
                name: $item['name'],
                label: $item['label'],
                value: $value          === null ? null : (float) $value,
                pass_grade: $passGrade === null ? null : (float) $passGrade,
                is_passed: $isPassed,
            );
        }

        return new EnrollmentGradeReportData(
            status: $status,
            status_translated: $translatedStatus,
            items: new DataCollection(EnrollmentGradeReportItemData::class, $reportItems),
        );
    }

    /**
     * @return DataCollection<int, TeacherDetailData>
     */
    private function buildTeachers(Enrollment $enrollment): DataCollection
    {
        $teachers = $enrollment->productDeliveryOption->teachers ?? collect([]);

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

        // Pending reviews already count as "reviewed": the student must not be offered
        // the form again while moderation is in flight. A rejected review frees them
        // to resubmit, matching SubmitReviewAction.
        $review = Review::query()
            ->where('user_id', $enrollment->customer_id)
            ->where('reviewable_type', $productable->getMorphClass())
            ->where('reviewable_id', $productable->id)
            ->whereIn('status', [ReviewStatusEnum::PENDING, ReviewStatusEnum::APPROVED])
            ->first();

        return new EnrollmentReviewInfoData(
            has_reviewed: $review !== null,
            review: $review ? ReviewData::fromModel($review) : null,
        );
    }

    private function buildCertificateInfo(Enrollment $enrollment, bool $resultsLocked): EnrollmentCertificateInfoData
    {
        $productable = $enrollment->productDeliveryOption->product->productable;

        $providesCertificate = (bool) ($productable->provides_certificate ?? false);

        if (! $providesCertificate) {
            return new EnrollmentCertificateInfoData(
                provides_certificate: false,
                is_available: false,
                certificate_url: null, // TODO: generate certificate URL when certificate generation is implemented
            );
        }

        $isEligible  = $this->surveyPolicy->allowsSurveyCompletion($enrollment);
        $isAvailable = $isEligible && ! $resultsLocked;

        return new EnrollmentCertificateInfoData(
            provides_certificate: true,
            is_available: $isAvailable,
            certificate_url: null, // TODO: generate certificate URL when certificate generation is implemented
        );
    }

    private function buildSurveyBlock(Enrollment $enrollment): EnrollmentSurveyBlockData
    {
        $state = $this->surveyPolicy->surveyState($enrollment);

        return new EnrollmentSurveyBlockData(
            state: $state,
            results_locked: $state->locksResults(),
            url: $this->surveyUrl->handle($enrollment),
            message: $state === EnrollmentSurveyStateEnum::AWAITING_SURVEY
                ? __('messages.enrollments.survey_awaiting')
                : null,
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
            $files = $productable->digitalAssets ?? collect([]);
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
                ['enrollment' => $enrollment->uuid, 'digitalAsset' => $asset->uuid],
                absolute: true
            ),
        ))->values()->all();

        return new DataCollection(DigitalAssetFileData::class, $items);
    }

    /**
     * @return DataCollection<int, EnrollmentQuizData>
     */
    private function buildQuizzes(Enrollment $enrollment, bool $resultsLocked): DataCollection
    {
        $provisioning = $enrollment->provisioning_data['providers'] ?? [];

        $activities = data_get($provisioning, 'moodle.sync.activities')
            ?? data_get($provisioning, 'moodle_quiz.sync.activities')
            ?? [];

        if (! is_array($activities)) {
            $activities = [];
        }

        $items = collect($activities)
            ->map(fn (array|object $activity): EnrollmentQuizData => new EnrollmentQuizData(
                cmid: (int) data_get($activity, 'cmid', 0),
                name: (string) data_get($activity, 'name', ''),
                type: (string) data_get($activity, 'type', ''),
                url: (string) data_get($activity, 'url', ''),
                state: (int) data_get($activity, 'state', 0),
                score: $resultsLocked ? null : (data_get($activity, 'score') ?? data_get($activity, 'grade')),
                timecompleted: data_get($activity, 'timecompleted') !== null
                    ? (int) data_get($activity, 'timecompleted')
                    : null,
            ))
            ->values()->all();

        return new DataCollection(EnrollmentQuizData::class, $items);
    }

    private function buildDeliveryAccess(Enrollment $enrollment, bool $resultsLocked): DeliveryAccessData
    {
        $deliveryOption = $enrollment->productDeliveryOption;
        $deliveryMethod = $deliveryOption->delivery_method;
        $details        = $deliveryOption->details_json               ?? [];
        $provisioning   = $enrollment->provisioning_data['providers'] ?? [];

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_NILIROOM => new DeliveryAccessData(
                type: $deliveryMethod->value,
                session_label: 'کلاس آنلاین',
                join_url_path: $this->joinUrlPath($enrollment),
                is_ready: filled(data_get($details, 'nili_room_id')),
            ),
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM => new DeliveryAccessData(
                type: $deliveryMethod->value,
                session_label: 'کلاس آنلاین',
                join_url_path: $this->joinUrlPath($enrollment),
                is_ready: data_get($provisioning, 'skyroom.status') === ProvisioningOutcomeStatusEnum::SUCCESS->value,
            ),
            DeliveryMethodEnum::LMS_MOODLE => new DeliveryAccessData(
                type: $deliveryMethod->value,
                course_url: data_get($provisioning, 'moodle.data.course_url'),
                completed: (bool) data_get($provisioning, 'moodle.sync.completed', false),
                course_grade: $resultsLocked ? null : data_get($provisioning, 'moodle.sync.course_grade'),
                is_ready: data_get($provisioning, 'moodle.status') === ProvisioningOutcomeStatusEnum::SUCCESS->value,
            ),
            DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER => new DeliveryAccessData(
                type: $deliveryMethod->value,
                license_key: data_get($provisioning, 'spotplayer.data.license_key'),
                player_url: data_get($provisioning, 'spotplayer.data.player_url'),
                is_ready: data_get($provisioning, 'spotplayer.status') === ProvisioningOutcomeStatusEnum::SUCCESS->value,
            ),
            DeliveryMethodEnum::IN_PERSON => new DeliveryAccessData(
                type: $deliveryMethod->value,
                address: data_get($details, 'address'),
                map_url: data_get($details, 'map_url'),
                is_ready: filled(data_get($details, 'address')),
            ),
            DeliveryMethodEnum::DIRECT_DOWNLOAD => new DeliveryAccessData(
                type: $deliveryMethod->value,
                is_ready: true,
            ),
        };
    }

    /**
     * Path of the student join endpoint for a live-session enrollment.
     */
    private function joinUrlPath(Enrollment $enrollment): string
    {
        return route('api.v1.shop.student.seminars.join', ['enrollment' => $enrollment->uuid], absolute: false);
    }
}
