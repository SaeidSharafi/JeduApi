<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Data\Shop\Teacher\TeacherSeminarData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Teacher - Seminar
 *
 * @authenticated user
 */
final class SeminarController extends Controller
{
    /**
     * List Teacher Seminars
     *
     * Returns a paginated list of seminars associated with the authenticated teacher.
     *
     * @queryParam per_page int Optional. Number of items per page. Default is the app's page size.
     *
     * @responseFile 200 resources/responses/shop/teacher/seminars.json
     *
     * @response 403 {"message": "Access Denied"}
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        /** @var Teacher|null $teacher */
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $seminars = $teacher->products()
            ->with('product')
            ->whereIn('delivery_method', DeliveryMethodEnum::getSeminars())
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(
            TeacherSeminarData::collect($seminars)
        );
    }
}
