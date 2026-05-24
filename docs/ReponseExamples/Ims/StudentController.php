<?php

declare(strict_types=1);

namespace App\Http\API\V2\Student;

use App\Enums\System\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentApiRequest;
use App\Models\Auth\User;
use App\Models\Students\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentController extends Controller
{
    public function __construct() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentApiRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();
        $phone         = $data['phone'];
        $national_code = $data['national_code'];
        $student       = Student::query()
            ->where('phone', $phone)
            ->orWhere('national_code', $national_code)
            ->first();

        $this->validateNationalCodeMatch($student, $data);
        $student = $this->updateStudent($student, $data);
        if ($student) {
            DB::commit();

            return response()->json([
                'message' => 'Student Exist',
                'data'    => [
                    'student_id' => $student->id,
                    'user_id'    => $student->user_id,
                ],
            ]);
        }

        $user = User::query()
            ->withTrashed()
            ->where('phone', $phone)
            ->first();
        if (! $user) {
            $user = User::create($data);
            $user->assignRole(RoleEnum::STUDENT->value);
        }
        if ($user->getDeletedAtColumn() !== null) {
            $user->restore();
        }

        $student = Student::create([
            'user_id' => $user->id,
            ...$data,
        ]);
        DB::commit();

        return response()->json([
            'message' => 'Student Created',
            'data'    => [
                'student_id' => $student->id,
                'user_id'    => $student->user_id,
            ],
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function validateNationalCodeMatch(?Student $student, array $data): void
    {
        if (! $student) {
            return;
        }
        if ($student->national_code !== (string) data_get($data, 'national_code')) {
            throw ValidationException::withMessages(['national_code' => 'خطا در تطابق کد ملی با شماره تلفن']);
        }
    }

    private function updateStudent(?Student $student, array $data): ?Student
    {
        if (! $student) {
            return null;
        }
        if (! $data['update_student']) {
            return $student;
        }
        $student->load('user');
        $phone = $data['phone'];
        if (
            $student->phone !== (string) $phone
            && empty($student->phone2)
        ) {
            $student->lockForUpdate();
            $student->phone2      = $student->phone;
            $student->phone       = $phone;
            $student->user->phone = $phone;
            $student->user->save();
            $student->save();
            $student->refresh();

            return $student;
        }

        if (
            $student->phone !== (string) $phone
            && ! empty($student->phone2)
            && $student->phone2 !== $phone
        ) {
            $student->lockForUpdate();
            $student->phone       = $phone;
            $student->user->phone = $phone;
            $student->user->save();
            $student->save();
        }

        return $student;

    }
}
