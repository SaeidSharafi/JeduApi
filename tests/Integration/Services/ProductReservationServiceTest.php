<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\ProductReservationService;

it('increments reserved_count on reserve', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 0]);

    app(ProductReservationService::class)->reserve($option->id, 3);

    expect($option->fresh()->reserved_count)->toBe(3);
});

it('consumes a reservation on payment completion', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 5]);

    app(ProductReservationService::class)->consume($option->id, 2);

    expect($option->fresh()->reserved_count)->toBe(3);
});

it('releases a reservation on cancellation', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 4]);

    app(ProductReservationService::class)->release($option->id, 4);

    expect($option->fresh()->reserved_count)->toBe(0);
});

it('never decrements reserved_count below zero', function (): void {
    $option = ProductDeliveryOption::factory()->create(['reserved_count' => 1]);

    app(ProductReservationService::class)->release($option->id, 10);

    expect($option->fresh()->reserved_count)->toBe(0);
});

it('accumulates multiple reserves', function (): void {
    $option  = ProductDeliveryOption::factory()->create(['reserved_count' => 0]);
    $service = app(ProductReservationService::class);

    $service->reserve($option->id, 2);
    $service->reserve($option->id, 3);

    expect($option->fresh()->reserved_count)->toBe(5);
});
