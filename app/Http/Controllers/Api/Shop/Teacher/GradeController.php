<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Integrations\ImsClientContract;
use App\Data\Shop\Teacher\StoreBulkGradeData;
use App\Data\Shop\Teacher\StoreGradeData;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Teacher - Grades
 *
 * APIs for managing course grade records via the IMS system.
 * All endpoints proxy requests to the external IMS grade service.
 *
 * @authenticated user
 */
final class GradeController extends Controller
{
    public function __construct(private readonly ImsClientContract $imsService) {}

    /**
     * List grades for a course.
     *
     * Retrieves grade records from the IMS system for the given course.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     *
     * @responseFile 200 resources/responses/shop/teacher/grade/index.json
     */
    public function index(Request $request, string $courseCode): JsonResponse
    {
        /** @var Teacher|null $teacher */
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        $data = $this->imsService->getGrades($courseCode, $teacherCivilId, $civilIdTypeEnum, $request->query());

        return response()->json($data);
    }

    /**
     * Store a single grade for a course.
     *
     * Creates a new grade record via the IMS system.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     *
     * @responseFile 200 resources/responses/shop/teacher/grade/store.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(StoreGradeData $data, string $courseCode): JsonResponse
    {        /** @var Teacher|null $teacher */
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        $response = $this->imsService->storeGrade($courseCode, $teacherCivilId, $civilIdTypeEnum, $data->toArray());

        return response()->json($response);
    }

    /**
     * Store bulk grades for a course.
     *
     * Creates multiple grade records in a single request via the IMS system.
     *
     * @urlParam courseCode string required The IMS course code. Example: IMS-100
     *
     * @responseFile 200 resources/responses/shop/teacher/grade/bulk.json
     * @responseFile 422 resources/responses/422.json
     */
    public function storeBulk(StoreBulkGradeData $data, string $courseCode): ApiResponseInterface
    {
        /** @var Teacher|null $teacher */
        $teacher = Auth::user()?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherCivilId  = Auth::user()?->civil_id;
        $civilIdTypeEnum = Auth::user()?->civil_id_type;

        try {
            $response = $this->imsService->storeBulkGrades($courseCode, $teacherCivilId, $civilIdTypeEnum, $data->toArray());
        } catch (UnrecoverableProvisioningException $e) {
            return apiResponse()->validationErrors(
                $e->getValidationErrors()
            );
        }

        return apiResponse()->success($response);
    }

    /**
     * Delete a course grade record.
     *
     * <aside class="notice">NOT IMPLEMENTED YET</aside>
     */
    public function destroy(int $gradeId): Response
    {
        // TODO implement the grade delete
        return response()->noContent();
    }
}
