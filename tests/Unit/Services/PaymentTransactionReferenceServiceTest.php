<?php

declare(strict_types=1);

use App\Models\PaymentTransaction;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    $this->service = new PaymentTransactionReferenceService();
});

it('generates first transaction reference from config start_from value', function (): void {
    Config::set('payments.transaction_reference.start_from', 200000001);

    $reference = $this->service->generate();

    expect($reference)->toBe('200000001');
});

it('generates sequential transaction references', function (): void {
    Config::set('payments.transaction_reference.start_from', 200000001);

    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000001',
    ]);

    $reference = $this->service->generate();

    expect($reference)->toBe('200000002');
});

it('generates correct reference after multiple transactions', function (): void {
    Config::set('payments.transaction_reference.start_from', 200000001);

    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000001',
    ]);
    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000002',
    ]);
    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000003',
    ]);

    $reference = $this->service->generate();

    expect($reference)->toBe('200000004');
});

it('handles non-sequential transaction references by using the last inserted', function (): void {
    Config::set('payments.transaction_reference.start_from', 200000001);

    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000001',
    ]);
    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000005',
    ]); // Gap in sequence
    $lastTransaction = PaymentTransaction::factory()->create([
        'transaction_reference' => '200000003',
    ]); // Last inserted, but not highest value

    $reference = $this->service->generate();

    // Should use last inserted (by ID) transaction's reference + 1
    expect($reference)->toBe('200000004');
});

it('generates unique references in concurrent scenarios', function (): void {
    Config::set('payments.transaction_reference.start_from', 200000001);

    // Create initial transaction
    PaymentTransaction::factory()->create([
        'transaction_reference' => '200000001',
    ]);

    // Simulate concurrent generation
    $references = [];
    for ($i = 0; $i < 5; $i++) {
        $reference    = $this->service->generate();
        $references[] = $reference;

        // Create the transaction immediately to simulate concurrent usage
        PaymentTransaction::factory()->create([
            'transaction_reference' => $reference,
        ]);
    }

    // All references should be unique
    expect(count($references))->toBe(count(array_unique($references)));
    expect($references)->toMatchArray(['200000002', '200000003', '200000004', '200000005', '200000006']);
});
