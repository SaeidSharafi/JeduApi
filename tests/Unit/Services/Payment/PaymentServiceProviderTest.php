<?php

declare(strict_types=1);

use App\Contracts\Payment\PaymentProcessorContract;
use App\Enums\Payment\PaymentMethodEnum;
use App\Providers\PaymentServiceProvider;
use App\Services\Payment\BankTransferPaymentProcessor;
use App\Services\Payment\PaymentProcessorFactory;
use App\Services\Payment\WalletPaymentProcessor;

describe('PaymentServiceProvider', function (): void {

    beforeEach(function (): void {
        // Register the PaymentServiceProvider
        $this->app->register(PaymentServiceProvider::class);
    });

    it('registers wallet payment processor as singleton', function (): void {
        $processor1 = $this->app->make(WalletPaymentProcessor::class);
        $processor2 = $this->app->make(WalletPaymentProcessor::class);

        expect($processor1)->toBeInstanceOf(WalletPaymentProcessor::class)
            ->and($processor1)->toBe($processor2) // Same instance (singleton)
            ->and($processor1)->toBeInstanceOf(PaymentProcessorContract::class);
    });

    it('registers bank transfer payment processor as singleton', function (): void {
        $processor1 = $this->app->make(BankTransferPaymentProcessor::class);
        $processor2 = $this->app->make(BankTransferPaymentProcessor::class);

        expect($processor1)->toBeInstanceOf(BankTransferPaymentProcessor::class)
            ->and($processor1)->toBe($processor2) // Same instance (singleton)
            ->and($processor1)->toBeInstanceOf(PaymentProcessorContract::class);
    });

    it('tags payment processors correctly', function (): void {
        $taggedProcessors = $this->app->tagged(PaymentServiceProvider::PAYMENT_PROCESSOR_TAG);
        $processors       = iterator_to_array($taggedProcessors);

        expect($processors)->toHaveCount(2);

        $processorClasses = array_map(fn ($processor): string|false => get_class($processor), $processors);

        expect($processorClasses)->toContain(WalletPaymentProcessor::class)
            ->and($processorClasses)->toContain(BankTransferPaymentProcessor::class);
    });

    it('registers payment processor factory as singleton', function (): void {
        $factory1 = $this->app->make(PaymentProcessorFactory::class);
        $factory2 = $this->app->make(PaymentProcessorFactory::class);

        expect($factory1)->toBeInstanceOf(PaymentProcessorFactory::class)
            ->and($factory1)->toBe($factory2); // Same instance (singleton)
    });

    it('payment processor factory is created with all tagged processors', function (): void {
        $factory = $this->app->make(PaymentProcessorFactory::class);

        // Test that factory can resolve all payment methods
        $walletProcessor       = $factory->make(PaymentMethodEnum::WALLET);
        $bankTransferProcessor = $factory->make(PaymentMethodEnum::BANK_TRANSFER);

        expect($walletProcessor)->toBeInstanceOf(WalletPaymentProcessor::class)
            ->and($bankTransferProcessor)->toBeInstanceOf(BankTransferPaymentProcessor::class);
    });

    it('ensures payment processor tag constant is accessible', function (): void {
        expect(PaymentServiceProvider::PAYMENT_PROCESSOR_TAG)->toBe('payment.processors');
    });

    it('verifies all processors implement the contract', function (): void {
        $taggedProcessors = $this->app->tagged(PaymentServiceProvider::PAYMENT_PROCESSOR_TAG);

        foreach ($taggedProcessors as $processor) {
            expect($processor)->toBeInstanceOf(PaymentProcessorContract::class);
        }
    });

    it('can resolve processors through dependency injection', function (): void {
        // Test that dependency injection works for other classes that depend on these processors
        $walletProcessor       = $this->app->make(WalletPaymentProcessor::class);
        $bankTransferProcessor = $this->app->make(BankTransferPaymentProcessor::class);

        expect($walletProcessor)->toBeInstanceOf(WalletPaymentProcessor::class)
            ->and($bankTransferProcessor)->toBeInstanceOf(BankTransferPaymentProcessor::class);

        // Verify they have their dependencies resolved
        expect($walletProcessor->canHandle(PaymentMethodEnum::WALLET))->toBeTrue()
            ->and($walletProcessor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeFalse()
            ->and($bankTransferProcessor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue()
            ->and($bankTransferProcessor->canHandle(PaymentMethodEnum::WALLET))->toBeFalse();
    });
});

describe('PaymentProcessorFactory Integration', function (): void {

    beforeEach(function (): void {
        $this->app->register(PaymentServiceProvider::class);
        $this->factory = $this->app->make(PaymentProcessorFactory::class);
    });

    it('returns correct processor for wallet payment method', function (): void {
        $processor = $this->factory->make(PaymentMethodEnum::WALLET);

        expect($processor)->toBeInstanceOf(WalletPaymentProcessor::class)
            ->and($processor->canHandle(PaymentMethodEnum::WALLET))->toBeTrue();
    });

    it('returns correct processor for bank transfer payment method', function (): void {
        $processor = $this->factory->make(PaymentMethodEnum::BANK_TRANSFER);

        expect($processor)->toBeInstanceOf(BankTransferPaymentProcessor::class)
            ->and($processor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue();
    });

    it('throws exception for unsupported payment method', function (): void {
        expect(fn () => $this->factory->make(PaymentMethodEnum::ONLINE_GATEWAY))
            ->toThrow(InvalidArgumentException::class, 'No payment processor found for method: online_gateway');
    });

    it('throws exception for cash on delivery method', function (): void {
        expect(fn () => $this->factory->make(PaymentMethodEnum::CASH_ON_DELIVERY))
            ->toThrow(InvalidArgumentException::class, 'No payment processor found for method: cash_on_delivery');
    });

    it('throws exception for no payment method', function (): void {
        expect(fn () => $this->factory->make(PaymentMethodEnum::NO_PAYMENT))
            ->toThrow(InvalidArgumentException::class, 'No payment processor found for method: no_payment');
    });

    it('returns same processor instance for multiple calls with same method', function (): void {
        // Since processors are registered as singletons, multiple calls should return same instance
        $processor1 = $this->factory->make(PaymentMethodEnum::WALLET);
        $processor2 = $this->factory->make(PaymentMethodEnum::WALLET);

        expect($processor1)->toBe($processor2);
    });

    it('can handle all registered payment methods sequentially', function (): void {
        $registeredMethods = [
            PaymentMethodEnum::WALLET,
            PaymentMethodEnum::BANK_TRANSFER,
        ];

        foreach ($registeredMethods as $method) {
            $processor = $this->factory->make($method);
            expect($processor)->toBeInstanceOf(PaymentProcessorContract::class)
                ->and($processor->canHandle($method))->toBeTrue();
        }
    });

    it('each processor handles only its designated method', function (): void {
        $walletProcessor       = $this->factory->make(PaymentMethodEnum::WALLET);
        $bankTransferProcessor = $this->factory->make(PaymentMethodEnum::BANK_TRANSFER);

        // Test wallet processor
        expect($walletProcessor->canHandle(PaymentMethodEnum::WALLET))->toBeTrue()
            ->and($walletProcessor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeFalse()
            ->and($walletProcessor->canHandle(PaymentMethodEnum::ONLINE_GATEWAY))->toBeFalse()
            ->and($walletProcessor->canHandle(PaymentMethodEnum::CASH_ON_DELIVERY))->toBeFalse()
            ->and($walletProcessor->canHandle(PaymentMethodEnum::NO_PAYMENT))->toBeFalse();

        // Test bank transfer processor
        expect($bankTransferProcessor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue()
            ->and($bankTransferProcessor->canHandle(PaymentMethodEnum::WALLET))->toBeFalse()
            ->and($bankTransferProcessor->canHandle(PaymentMethodEnum::ONLINE_GATEWAY))->toBeFalse()
            ->and($bankTransferProcessor->canHandle(PaymentMethodEnum::CASH_ON_DELIVERY))->toBeFalse()
            ->and($bankTransferProcessor->canHandle(PaymentMethodEnum::NO_PAYMENT))->toBeFalse();
    });

    it('factory maintains processor selection consistency', function (): void {
        // Test that factory always returns the same processor type for the same method
        for ($i = 0; $i < 10; $i++) {
            $walletProcessor       = $this->factory->make(PaymentMethodEnum::WALLET);
            $bankTransferProcessor = $this->factory->make(PaymentMethodEnum::BANK_TRANSFER);

            expect($walletProcessor)->toBeInstanceOf(WalletPaymentProcessor::class)
                ->and($bankTransferProcessor)->toBeInstanceOf(BankTransferPaymentProcessor::class);
        }
    });

    it('verifies error message format for unsupported methods', function (): void {
        $unsupportedMethods = [
            PaymentMethodEnum::ONLINE_GATEWAY,
            PaymentMethodEnum::CASH_ON_DELIVERY,
            PaymentMethodEnum::NO_PAYMENT,
        ];

        foreach ($unsupportedMethods as $method) {
            try {
                $this->factory->make($method);
                $this->fail("Expected InvalidArgumentException for method: {$method->value}");
            } catch (InvalidArgumentException $e) {
                expect($e->getMessage())->toBe("No payment processor found for method: {$method->value}");
            }
        }
    });
});
