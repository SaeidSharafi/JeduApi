<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Data\Shop\Student\Order\OrderData;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;

final class CheckoutResponseData extends Data
{
    public function __construct(
        public readonly OrderData $order,
        public readonly ?string $redirect_url = null,
        public readonly ?array $redirect_data = null,
        #[In(['GET', 'POST'])]
        public readonly string $redirect_method = 'GET',
    ) {}

    /**
     * Create response for completed checkout (single-step payment or free order).
     */
    public static function completed(OrderData $order): self
    {
        return new self(
            order: $order,
            redirect_url: null,
            redirect_data: null,
            redirect_method: 'GET'
        );
    }

    /**
     * Create response for checkout requiring redirect (multi-step payment gateway).
     */
    public static function withRedirect(
        OrderData $order,
        string $redirectUrl,
        ?array $redirectData = null,
        string $method = 'GET'
    ): self {
        return new self(
            order: $order,
            redirect_url: $redirectUrl,
            redirect_data: $redirectData,
            redirect_method: $method
        );
    }
}
