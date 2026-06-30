<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Student\GetEnrollmentDetailAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\Enrollment\EnrollmentData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;
use App\Jobs\Provisioning\SyncMoodleProgressJob;
use App\Models\Enrollment;
use Illuminate\Support\Facades\RateLimiter;

/**
 * @group Shop - Student - Courses
 *
 * @authenticated user
 */
final class EnrollmentController extends Controller
{
    /**
     * Get a paginated list of the authenticated user's enrollments.
     *
     * List enrollments for the authenticated user, with optional filtering by fulfillment type and product name.
     *
     * @queryParam filter[fulfillment_type] string Filter by fulfillment type. Example: digital
     * @queryParam filter[name] string Filter by product name. Example: Course Name
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 resources/responses/shop/enrollments/index.json
     */
    public function index(): ApiResponseInterface
    {
        $filters     = request()->array('filter', []);
        $enrollments = auth()->user()->enrollments()
            ->withWhereHas(
                'productDeliveryOption', function ($query) use ($filters): void {
                    $query->when(data_get($filters, 'fulfillment_type'), function ($query, $fulfillmentType): void {
                        $query->where('fulfillment_type', $fulfillmentType);
                    })->withWhereHas(
                        'product', function ($query) use ($filters): void {
                            $query
                                ->when(data_get($filters, 'name'), function ($query, $name): void {
                                    $query->whereLike('name', "%{$name}%");
                                })
                                ->with(['productableWithAllRelations']);
                        })->with('teachers.media');
                }
            )
            ->with(['orderItem.vendor'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return apiResponse()->success(EnrollmentData::collect($enrollments));
    }

    /**
     * Show a specific enrollment.
     *
     * @responseFile 200 resources/responses/shop/enrollments/show.json
     *
     * @response 404 {"message": "Enrollment not found."}
     */
    public function show(Enrollment $enrollment, GetEnrollmentDetailAction $action): ApiResponseInterface
    {
        if (auth()->user()->id !== $enrollment->customer_id) {
            return apiResponse()->notFound(__('messages.enrollments.not_found'));
        }
        $enrollment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem.vendor',
        ]);

        $this->triggerMoodleSwr($enrollment);

        return apiResponse()->success($action->handle($enrollment));
    }

    private function triggerMoodleSwr(Enrollment $enrollment): void
    {
        $deliveryOption = $enrollment->productDeliveryOption;

        if ($deliveryOption?->delivery_method === DeliveryMethodEnum::LMS_MOODLE) {
            $rawCourseId = data_get($deliveryOption->details_json, 'moodle_course_id');
            $rawUserId   = data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_id');

            if (is_numeric($rawCourseId) && is_numeric($rawUserId)) {
                $this->dispatchMoodleSync(
                    $enrollment,
                    (int) $rawCourseId,
                    (int) $rawUserId,
                    'moodle'
                );
            }
        }

        if ($deliveryOption?->delivery_method !== DeliveryMethodEnum::LMS_MOODLE
            && isset($deliveryOption->details_json['moodle_quiz_course_id'])
        ) {
            $rawCourseId = data_get($deliveryOption->details_json, 'moodle_quiz_course_id');
            $rawUserId   = data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_user_id');

            if (is_numeric($rawCourseId) && is_numeric($rawUserId)) {
                $this->dispatchMoodleSync(
                    $enrollment,
                    (int) $rawCourseId,
                    (int) $rawUserId,
                    'moodle_quiz'
                );
            }
        }
    }

    private function dispatchMoodleSync(
        Enrollment $enrollment,
        int $moodleCourseId,
        int $moodleUserId,
        string $providerKey
    ): void {
        if ($moodleCourseId <= 0 || $moodleUserId <= 0) {
            return;
        }

        $throttleKey = "throttle:moodle-sync:{$enrollment->id}:{$moodleCourseId}:{$moodleUserId}:{$providerKey}";

        RateLimiter::attempt(
            $throttleKey,
            maxAttempts: 1,
            callback: fn (): \Illuminate\Foundation\Bus\PendingDispatch => dispatch(new SyncMoodleProgressJob(
                $enrollment->id,
                $moodleCourseId,
                $moodleUserId,
                $providerKey
            )),
            decaySeconds: 300
        );
    }
}
