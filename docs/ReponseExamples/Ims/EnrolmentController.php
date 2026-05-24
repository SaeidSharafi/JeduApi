<?php

declare(strict_types=1);

namespace App\Http\API\V2\Student;

use App\Actions\Students\EnrolStudentAction;
use App\DTO\FullEnrolmentDTO;
use App\Enums\Financial\ReceiptTypeEnum;
use App\Enums\Student\EnrolmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreEnrolmentApiRequest;
use App\Models\Courses\Course;
use App\Models\Financial\BankAccount;
use App\Models\Financial\PaymentType\Receipt;
use App\Models\Financial\Transaction;
use App\Models\Students\Enrolment;
use App\Models\Students\Student;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

final class EnrolmentController extends Controller
{
    public function __construct(
        private readonly EnrolStudentAction $enrolStudentAction
    ) {}

    /**
     * Store a newly created resource in storage.
     */
    public function __invoke(StoreEnrolmentApiRequest $request, Student $student): JsonResponse
    {
        $data = $request->validated();

        DB::beginTransaction();
        $course    = Course::where('code', $data['course_code'])->firstOrFail();
        $enrolment = Enrolment::withTrashed()->where('course_id', $course->id)
            ->where('student_id', $student->id)->first();
        if ($enrolment) {
            return response()->json(
                [
                    'message' => 'Enrollment exist',
                    'data'    => [
                        'enrolment_id' => $enrolment->id,
                        'created_at'   => $enrolment->created_at->format('Y-m-d H:i:s'),
                    ],
                ]
            );
        }
        $enrolData = [
            'course_price'    => $course->price,
            'course_id'       => $course->id,
            'student_id'      => $student->id,
            'status'          => EnrolmentStatusEnum::REGISTERED->value,
            'payed_amount'    => data_get($data, 'payment.amount'),
            'discount_type'   => data_get($data, 'payment.discount_type'),
            'discount_amount' => data_get($data, 'payment.discount_amount'),
            'discount_code'   => data_get($data, 'payment.discount_code'),
            'note'            => data_get($data, 'note'),
        ];
        $enrolment = $this->enrolStudentAction->handle(FullEnrolmentDTO::fromArray($enrolData));
        $enrolment->load('payment');
        $bankAccount = BankAccount::query()
            ->where('account_number', data_get($data, 'payment.bank_account_number'))
            ->first();
        $bankAccountId = $bankAccount?->id ?: 10;
        $payway        = Receipt::create([
            'type'            => ReceiptTypeEnum::GATEWAY,
            'amount'          => $data['payment']['amount'],
            'bank_account_id' => $bankAccountId,
            'receipt_code'    => $data['payment']['tracking_code'],
            'receipt_date'    => $data['payment']['date'],
            'note'            => data_get($data, 'note', 'پرداخت آنلاین'),
        ]);
        Transaction::create([
            'amount'      => data_get($data, 'payment.amount'),
            'payment_id'  => $enrolment->payment->id,
            'payway_id'   => $payway->id,
            'payway_type' => Receipt::class,
            'note'        => data_get($data, 'note', 'پرداخت آنلاین'),
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Enrollment Created',
            'data'    => [
                'enrolment_id' => $enrolment->id,
                'created_at'   => $enrolment->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
