<?php

declare(strict_types=1);

use App\Contracts\Payment\PaymentProcessorContract;
use App\Enums\Payment\PaymentMethodEnum;
use App\Services\Payment\PaymentProcessorFactory;
use Tests\Fakes\Payment\MockBankTransferProcessor;
use Tests\Fakes\Payment\MockUnsupportedProcessor;
use Tests\Fakes\Payment\MockWalletProcessor;

describe('PaymentProcessorFactory', function (): void {

    beforeEach(function (): void {
        // Create simple mock processors for testing
        $this->walletProcessor       = new MockWalletProcessor();
        $this->bankTransferProcessor = new MockBankTransferProcessor();

        $this->processors = [
            $this->walletProcessor,
            $this->bankTransferProcessor,
        ];

        $this->factory = new PaymentProcessorFactory($this->processors);
    });

    it('constructs with array of processors', function (): void {
        $factory = new PaymentProcessorFactory($this->processors);
        expect($factory)->toBeInstanceOf(PaymentProcessorFactory::class);
    });

    it('constructs with iterator of processors', function (): void {
        $iterator = new ArrayIterator($this->processors);
        $factory  = new PaymentProcessorFactory($iterator);
        expect($factory)->toBeInstanceOf(PaymentProcessorFactory::class);
    });

    it('enforces type safety for processors in constructor', function (): void {
        $invalidProcessors = [
            $this->walletProcessor,
            'invalid_processor', // This should cause a type error
        ];

        expect(fn (): \App\Services\Payment\PaymentProcessorFactory => new PaymentProcessorFactory($invalidProcessors))
            ->toThrow(TypeError::class);
    });

    it('returns correct processor for wallet method', function (): void {
        $processor = $this->factory->make(PaymentMethodEnum::WALLET);

        expect($processor)->toBe($this->walletProcessor)
            ->and($processor->canHandle(PaymentMethodEnum::WALLET))->toBeTrue();
    });

    it('returns correct processor for bank transfer method', function (): void {
        $processor = $this->factory->make(PaymentMethodEnum::BANK_TRANSFER);

        expect($processor)->toBe($this->bankTransferProcessor)
            ->and($processor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue();
    });

    it('throws exception when no processor can handle the method', function (): void {
        expect(fn () => $this->factory->make(PaymentMethodEnum::ONLINE_GATEWAY))
            ->toThrow(InvalidArgumentException::class, 'No payment processor found for method: online_gateway');
    });

    it('returns first matching processor when multiple processors can handle same method', function (): void {
        // Create a second wallet processor
        $secondWalletProcessor = new MockWalletProcessor();

        $processorsWithDuplicate = [
            $this->walletProcessor,
            $secondWalletProcessor,
            $this->bankTransferProcessor,
        ];

        $factory   = new PaymentProcessorFactory($processorsWithDuplicate);
        $processor = $factory->make(PaymentMethodEnum::WALLET);

        // Should return the first matching processor
        expect($processor)->toBe($this->walletProcessor);
    });

    it('handles empty processor list gracefully', function (): void {
        $factory = new PaymentProcessorFactory([]);

        expect(fn (): \App\Contracts\Payment\PaymentProcessorContract => $factory->make(PaymentMethodEnum::WALLET))
            ->toThrow(InvalidArgumentException::class, 'No payment processor found for method: wallet');
    });

    it('maintains processor order during iteration', function (): void {
        // Create processors in specific order where all can handle the same method
        $firstWalletProcessor  = new MockWalletProcessor();
        $secondWalletProcessor = new MockWalletProcessor();
        $thirdWalletProcessor  = new MockWalletProcessor();

        $factory = new PaymentProcessorFactory([
            $firstWalletProcessor,
            $secondWalletProcessor,
            $thirdWalletProcessor,
        ]);

        $processor = $factory->make(PaymentMethodEnum::WALLET);
        expect($processor)->toBe($firstWalletProcessor);
    });

    it('provides meaningful error messages for all unsupported methods', function (): void {
        $unsupportedMethods = [
            ['method' => PaymentMethodEnum::ONLINE_GATEWAY, 'expected' => 'online_gateway'],
            ['method' => PaymentMethodEnum::CASH_ON_DELIVERY, 'expected' => 'cash_on_delivery'],
            ['method' => PaymentMethodEnum::NO_PAYMENT, 'expected' => 'no_payment'],
        ];

        foreach ($unsupportedMethods as $testCase) {
            try {
                $this->factory->make($testCase['method']);
                $this->fail("Expected exception for method: {$testCase['expected']}");
            } catch (InvalidArgumentException $e) {
                expect($e->getMessage())->toBe("No payment processor found for method: {$testCase['expected']}");
            }
        }
    });

    it('works with single processor', function (): void {
        $singleProcessorFactory = new PaymentProcessorFactory([$this->walletProcessor]);

        $processor = $singleProcessorFactory->make(PaymentMethodEnum::WALLET);
        expect($processor)->toBe($this->walletProcessor);

        expect(fn (): \App\Contracts\Payment\PaymentProcessorContract => $singleProcessorFactory->make(PaymentMethodEnum::BANK_TRANSFER))
            ->toThrow(InvalidArgumentException::class);
    });

    it('works with processor that always returns false', function (): void {
        $unsupportedProcessor = new MockUnsupportedProcessor();
        $factory              = new PaymentProcessorFactory([
            $unsupportedProcessor,
            $this->walletProcessor,
        ]);

        // Should skip the unsupported processor and find the wallet processor
        $processor = $factory->make(PaymentMethodEnum::WALLET);
        expect($processor)->toBe($this->walletProcessor);
    });

    it('validates all processors implement the contract', function (): void {
        foreach ($this->processors as $processor) {
            expect($processor)->toBeInstanceOf(PaymentProcessorContract::class);
        }
    });

    it('handles method comparison correctly', function (): void {
        // Test that processors correctly identify their supported methods
        expect($this->walletProcessor->canHandle(PaymentMethodEnum::WALLET))->toBeTrue()
            ->and($this->walletProcessor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeFalse()
            ->and($this->walletProcessor->canHandle(PaymentMethodEnum::ONLINE_GATEWAY))->toBeFalse()
            ->and($this->walletProcessor->canHandle(PaymentMethodEnum::CASH_ON_DELIVERY))->toBeFalse()
            ->and($this->walletProcessor->canHandle(PaymentMethodEnum::NO_PAYMENT))->toBeFalse();

        expect($this->bankTransferProcessor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue()
            ->and($this->bankTransferProcessor->canHandle(PaymentMethodEnum::WALLET))->toBeFalse()
            ->and($this->bankTransferProcessor->canHandle(PaymentMethodEnum::ONLINE_GATEWAY))->toBeFalse()
            ->and($this->bankTransferProcessor->canHandle(PaymentMethodEnum::CASH_ON_DELIVERY))->toBeFalse()
            ->and($this->bankTransferProcessor->canHandle(PaymentMethodEnum::NO_PAYMENT))->toBeFalse();
    });

    it('returns different processors for different methods', function (): void {
        $walletProcessor       = $this->factory->make(PaymentMethodEnum::WALLET);
        $bankTransferProcessor = $this->factory->make(PaymentMethodEnum::BANK_TRANSFER);

        expect($walletProcessor)->not()->toBe($bankTransferProcessor)
            ->and($walletProcessor)->toBeInstanceOf(MockWalletProcessor::class)
            ->and($bankTransferProcessor)->toBeInstanceOf(MockBankTransferProcessor::class);
    });
});
