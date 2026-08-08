<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Teacher\TeacherCourseItemData;
use App\Enums\MediaTagEnum;
use App\Http\Controllers\Controller;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use App\Services\Integrations\ImsService;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Teacher - Courses
 *
 * APIs for retrieving teacher course listings from the IMS system.
 * Courses are enriched with local product data (images) when available.
 *
 * @authenticated user
 */
final class CourseController extends Controller
{
    public function __construct(private readonly ImsService $imsService) {}

    /**
     * Get the authenticated teacher's courses.
     *
     * Fetches the teacher's courses from the IMS system and enriches them with
     * local product data (product image) when a matching ProductDeliveryOption exists.
     *
     * @queryParam period string Filter courses by time period. Supported values: current, past. Example: current
     *
     * @responseFile 200 resources/responses/shop/teacher/courses.json
     */
    public function index(): ApiResponseInterface
    {
        /** @var Teacher|null $teacher */
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        $queryParams = request()->query();

        $response = $this->imsService->getTeacherCourses($teacherCivilId, $civilIdTypeEnum, $queryParams);
        $courses  = data_get($response, 'data', []);

        if (! is_array($courses)) {
            $courses = [];
        }

        // Build a lookup of PDOs keyed by ims_course_code
        $codes = collect($courses)->pluck('code')->filter()->values()->all();
        $pdos  = ProductDeliveryOption::query()
            ->whereIn('details_json->ims_course_code', $codes)
            ->with('product.productable.media')
            ->get()
            ->keyBy(fn (ProductDeliveryOption $pdo): string => $pdo->ims_course_code);

        $result = collect($courses)->map(function (array $course) use ($pdos): TeacherCourseItemData {
            $code  = $course['code'] ?? '';
            $image = null;

            /** @var ProductDeliveryOption|null $pdo */
            $pdo = $code ? $pdos->get($code) : null;

            if ($pdo) {
                $media = $pdo->product->productable?->getAllMedia() ?? [];
                $cover = $media[MediaTagEnum::COVER->value]         ?? null;

                if ($cover && isset($cover[0])) {
                    $image = [
                        'url'       => $cover[0]['url']       ?? null,
                        'thumbnail' => $cover[0]['thumbnail'] ?? null,
                        'alt'       => $cover[0]['alt']       ?? null,
                    ];
                }
            }

            return new TeacherCourseItemData(
                code: $code,
                name: $course['name'] ?? '',
                start_date: $course['start_date'] ? verta($course['start_date'])->formatDate() : null,
                end_date: $course['end_date'] ? verta($course['end_date'])->formatDate() : null,
                is_current: (bool) ($course['is_current'] ?? false),
                has_grades_enabled: (bool) ($course['has_grade_enabled'] ?? false),
                has_attendance_enabled: (bool) ($course['has_attendance_enabled'] ?? false),
                product_image: $image,
                product_delivery_option_uuid: $pdo?->uuid,
            );
        });

        return apiResponse()->success($result->values()->all());
    }
}
