<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Integrations\ImsClientContract;
use App\Data\Shop\Teacher\DeleteAttendanceData;
use App\Data\Shop\Teacher\ShowAttendanceData;
use App\Data\Shop\Teacher\StoreAttendanceData;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Teacher - Attendance
 *
 * APIs for managing course attendance records via the IMS system.
 * All endpoints proxy requests to the external IMS attendance service.
 *
 * @authenticated user
 */
final class AttendanceController extends Controller
{
    public function __construct(private readonly ImsClientContract $imsService) {}

    /**
     * List attendance records for a course.
     *
     * Retrieves attendance records from the IMS system for the given course.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     *
     * @responseFile 200 resources/responses/shop/teacher/attendance/index.json
     */
    public function index(ShowAttendanceData $request, string $courseCode): ApiResponseInterface
    {
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        $data = $this->imsService->getAttendance($courseCode, $teacherCivilId, $civilIdTypeEnum, $request->all());

        return apiResponse()->success($data);
    }

    /**
     * Create attendance records for a course.
     *
     * Stores new attendance records via the IMS system.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     *
     * @responseFile 200 resources/responses/shop/teacher/attendance/store.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(StoreAttendanceData $data, string $courseCode): ApiResponseInterface
    {
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        try {
            $response = $this->imsService->storeAttendance($courseCode, $teacherCivilId, $civilIdTypeEnum, $data->toArray());
        } catch (UnrecoverableProvisioningException $e) {
            return apiResponse()->validationErrors(
                $e->getValidationErrors()
            );
        }

        return apiResponse()->success($response);
    }

    /**
     * Update attendance records for a course.
     *
     * Updates existing attendance records via the IMS system.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     *
     * @responseFile 200 resources/responses/shop/teacher/attendance/update.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(StoreAttendanceData $data, string $courseCode): ApiResponseInterface
    {
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        try {
            $response = $this->imsService->updateAttendance($courseCode, $teacherCivilId, $civilIdTypeEnum, $data->toArray());
        } catch (UnrecoverableProvisioningException $e) {
            return apiResponse()->validationErrors(
                $e->getValidationErrors()
            );
        }

        return apiResponse()->success($response);
    }

    /**
     * Delete attendance records for a course.
     *
     * Removes attendance records via the IMS system.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     */
    public function destroy(DeleteAttendanceData $attendanceData, string $courseCode): ApiResponseInterface
    {
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        $response = $this->imsService->destroyAttendance($courseCode, $teacherCivilId, $civilIdTypeEnum, $attendanceData->toArray());

        return apiResponse()->success($response);
    }
}
