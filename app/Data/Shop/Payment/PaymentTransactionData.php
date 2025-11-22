<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class PaymentTransactionData extends Data
{
    public function __construct(
        public string $transaction_reference,
        public int $attempt_number,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentTransactionStatusEnum $status,
        public array|Optional|null $gateway_request = null,
        public array|Optional|null $gateway_response = null,
        public ?Verta $initiated_at = null,
        public ?Verta $completed_at = null,
        public ?string $error_code = null,
        public ?string $error_message = null,
        public ?string $ip_address = null,
    ) {}
}
