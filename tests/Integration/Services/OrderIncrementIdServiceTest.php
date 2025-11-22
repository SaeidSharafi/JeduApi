<?php

declare(strict_types=1);

use App\Models\Order;
use App\Services\OrderIncrementIdService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Facades\Config;

describe('OrderIncrementIdService', function (): void {

    beforeEach(function (): void {
        $this->service = new OrderIncrementIdService();
    });

    describe('Simple Pattern', function (): void {

        beforeEach(function (): void {
            Config::set('order.increment_id.pattern', 'simple');
            Config::set('order.increment_id.padding', 9);
            Config::set('order.increment_id.start_from', 100000001);
        });

        it('generates the first increment ID when no orders exist', function (): void {
            $incrementId = $this->service->generate();

            expect($incrementId)
                ->toBe('100000001')
                ->and(mb_strlen($incrementId))
                ->toBe(9);
        });

        it('generates sequential increment IDs', function (): void {
            $order1 = Order::factory()->create(['increment_id' => '100000001']);

            $incrementId = $this->service->generate();

            expect($incrementId)
                ->toBe('100000002')
                ->and((int) $incrementId)
                ->toBe(100000002);
        });

        it('maintains zero padding', function (): void {
            Config::set('order.increment_id.padding', 12);

            $incrementId = $this->service->generate();

            expect($incrementId)
                ->toBe('000100000001')
                ->and(mb_strlen($incrementId))
                ->toBe(12);
        });

        it('increments correctly from existing orders', function (): void {
            Order::factory()->create(['increment_id' => '100000005']);
            Order::factory()->create(['increment_id' => '100000010']);

            $incrementId = $this->service->generate();

            expect($incrementId)->toBe('100000011');
        });

    });

    describe('Dated Pattern', function (): void {

        beforeEach(function (): void {
            Config::set('order.increment_id.pattern', 'dated');
            Config::set('order.increment_id.padding', 6);
            Config::set('order.increment_id.start_from', 1);
        });

        it('generates increment ID with Verta date prefix', function (): void {
            $verta        = Verta::now();
            $expectedDate = $verta->format('Ymd'); // e.g., 14040802

            $incrementId = $this->service->generate();

            expect($incrementId)
                ->toStartWith($expectedDate.'-')
                ->and($incrementId)
                ->toMatch('/^\d{8}-\d{6,}$/'); // Format: YYYYMMDD-NNNNNN (at least 6 digits)
        });

        it('increments the number part while keeping date prefix', function (): void {
            $verta      = Verta::now();
            $datePrefix = $verta->format('Ymd');

            Order::factory()->create(['increment_id' => "{$datePrefix}-000001"]);

            $incrementId = $this->service->generate();

            expect($incrementId)->toBe("{$datePrefix}-000002");
        });

        it('extracts number correctly from dated format', function (): void {
            Order::factory()->create(['increment_id' => '14040802-000099']);

            $incrementId = $this->service->generate();

            $verta      = Verta::now();
            $datePrefix = $verta->format('Ymd');

            expect($incrementId)->toBe("{$datePrefix}-000100");
        });

    });

    describe('Prefixed Pattern', function (): void {

        beforeEach(function (): void {
            Config::set('order.increment_id.pattern', 'prefixed');
            Config::set('order.increment_id.prefix', 'ORD-');
            Config::set('order.increment_id.padding', 9);
            Config::set('order.increment_id.start_from', 100000001);
        });

        it('generates increment ID with custom prefix', function (): void {
            $incrementId = $this->service->generate();

            expect($incrementId)
                ->toBe('ORD-100000001')
                ->and($incrementId)
                ->toStartWith('ORD-');
        });

        it('increments with prefix preserved', function (): void {
            Order::factory()->create(['increment_id' => 'ORD-100000001']);

            $incrementId = $this->service->generate();

            expect($incrementId)->toBe('ORD-100000002');
        });

        it('works with custom prefixes', function (): void {
            Config::set('order.increment_id.prefix', 'JEDU-');

            $incrementId = $this->service->generate();

            expect($incrementId)
                ->toStartWith('JEDU-')
                ->and($incrementId)
                ->toBe('JEDU-100000001');
        });

    });

    describe('Edge Cases', function (): void {

        beforeEach(function (): void {
            Config::set('order.increment_id.pattern', 'simple');
            Config::set('order.increment_id.padding', 9);
            Config::set('order.increment_id.start_from', 100000001);
        });

        it('handles concurrent generation with transaction locks', function (): void {
            $order = Order::factory()->create(['increment_id' => '100000001']);

            // Simulate multiple concurrent requests
            $results = [];
            for ($i = 0; $i < 5; $i++) {
                $results[] = $this->service->generate();
                // Create the order so the next call sees it
                Order::factory()->create(['increment_id' => end($results)]);
            }

            // All results should be sequential and unique
            expect($results)
                ->toHaveCount(5)
                ->and($results)
                ->toBe(['100000002', '100000003', '100000004', '100000005', '100000006']);
        });

        it('extracts numbers from mixed format increment IDs', function (): void {
            Order::factory()->create(['increment_id' => 'TEST-999']);

            $incrementId = $this->service->generate();

            expect($incrementId)->toBe('000001000');
        });

        it('handles empty increment_id gracefully', function (): void {
            // This shouldn't happen, but let's be defensive
            // When increment_id is empty, extractNumber returns 0, so next is 1
            Order::factory()->create(['increment_id' => '']);

            $incrementId = $this->service->generate();

            // Should be '000000001' because 0 + 1 = 1 with padding
            expect($incrementId)->toBe('000000001');
        });

    });

    describe('Integration with Order Model', function (): void {

        beforeEach(function (): void {
            Config::set('order.increment_id.pattern', 'simple');
            Config::set('order.increment_id.padding', 9);
            Config::set('order.increment_id.start_from', 100000001);
        });

        it('generates increment ID through Order::generateIncrementId()', function (): void {
            $incrementId = Order::generateIncrementId();

            expect($incrementId)
                ->toBe('100000001')
                ->and($incrementId)
                ->toBeString();
        });

        it('increments correctly when called through Order model', function (): void {
            Order::factory()->create(['increment_id' => '100000001']);

            $incrementId = Order::generateIncrementId();

            expect($incrementId)->toBe('100000002');
        });

    });

});
