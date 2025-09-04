<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Admin\Payment\CreatePaymentAction;
use App\Services\Payment\BankTransferPaymentProcessor;
use App\Services\Payment\PaymentProcessorFactory;
use App\Services\Payment\WalletPaymentProcessor;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{

    public const PAYMENT_PROCESSOR_TAG = 'payment.processors';
    /**
     * Register services.
     */
    public function register(): void
    {
        // 1. Register each individual processor as a singleton
        $this->app->singleton(WalletPaymentProcessor::class);
        $this->app->singleton(BankTransferPaymentProcessor::class);

        $this->app->tag([
            WalletPaymentProcessor::class,
            BankTransferPaymentProcessor::class,
        ], self::PAYMENT_PROCESSOR_TAG);

        $this->app->singleton(PaymentProcessorFactory::class, function ($app) {
            return new PaymentProcessorFactory($app->tagged(self::PAYMENT_PROCESSOR_TAG));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
