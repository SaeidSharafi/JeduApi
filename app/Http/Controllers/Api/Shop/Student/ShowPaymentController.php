<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Payment\PaymentData;
use App\Data\Shop\Student\Order\OrderData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Student - Payments
 *
 * @authenticated
 */
final class ShowPaymentController extends Controller
{
    /**
     * Retrun list of payments
     *
     * @responseFile resources/responses/shop/payment/index.json
     */
    public function index(Request $request): ApiResponseInterface
    {

        $user    = Auth::guard('user')->user();
        $purpose = PaymentPurposeEnum::tryFrom($request->string('purpose')->toString());
        $payments = Payment::query()
            ->where('customer_id', $user->id)
            ->when($purpose, fn ($query) => $query->where('purpose', $purpose))
            ->with('transactions')
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return apiResponse()->success(PaymentData::collect($payments));

    }

    /**
     * Show a Specific payment
     *
     * @responseFile resources/responses/shop/payment/show.json
     */
    public function show(string $uuid): ApiResponseInterface
    {
        $user    = Auth::guard('user')->user();
        $payment = Payment::query()
            ->where('customer_id', $user->id)
            ->where('uuid', $uuid)
            ->with('transactions')
            ->firstOrFail();

        return apiResponse()->success(PaymentData::from($payment));
    }
}
