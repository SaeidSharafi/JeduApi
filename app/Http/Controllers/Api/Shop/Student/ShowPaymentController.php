<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Payment\PaymentData;
use App\Data\Shop\Student\Payment\PaymentListData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Optional;

/**
 * @group Shop - Student - Payments
 *
 * @authenticated
 */
final class ShowPaymentController extends Controller
{
    /**
     * Return list of payments
     *
     * @responseFile resources/responses/shop/payment/index.json
     */
    public function index(PaymentListData $data): ApiResponseInterface
    {

        $user    = Auth::guard('user')->user();
        $purpose = $data->purpose instanceof Optional
            ? null
            : PaymentPurposeEnum::tryFrom((string) $data->purpose);
        $payments = Payment::query()
            ->where('customer_id', $user->id)
            ->when($purpose, fn ($query) => $query->where('purpose', $purpose))
            ->with('transactions')
            ->latest()
            ->paginate($data->per_page)
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
