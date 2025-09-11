<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Enums\Payment\PaymentMethodEnum;
use InvalidArgumentException;

final readonly class PaymentProcessorFactory
{
    /**
     * @param  iterable<PaymentProcessorContract>  $processors
     */
    private iterable $processors;

    /**
     * @param  iterable<PaymentProcessorContract>  $processors  Processors injected by the service container.
     */
    public function __construct(iterable $processors)
    {
        // Ensure all items are of the correct type
        $this->processors = (static function (PaymentProcessorContract ...$processors) {
            return $processors;
        })(...$processors);
    }

    public function make(PaymentMethodEnum $method): PaymentProcessorContract
    {
        foreach ($this->processors as $processor) {
            if ($processor->canHandle($method)) {
                return $processor;
            }
        }

        throw new InvalidArgumentException("No payment processor found for method: {$method->value}");
    }
}
