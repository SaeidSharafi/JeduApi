<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Data\Admin\MediaData;
use App\Data\Shop\Teacher\TeacherDetailData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;
use App\Models\Teacher;

/**
 * @group Shop - Teacher
 *
 * APIs for viewing teacher profiles.
 */
final class TeacherController extends Controller
{
    /**
     * Retrieve the detailed profile for a specific teacher.
     *
     * This is called when a user clicks on a teacher's card.
     *
     * @responseFile storage/responses/shop/teacher/show.json
     * @responseFile 404 responses/404.json
     */
    public function show(Teacher $teacher): ApiSuccessResponse
    {
        return response()->success(TeacherDetailData::from($teacher));
    }
}
