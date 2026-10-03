<?php

declare(strict_types=1);

use App\Actions\Admin\ProductDeliveryOption\DeleteProductDeliveryOptionAction;
use App\Enums\EnrollmentStatusEnum;
use App\Events\ProductCacheInvalidated;
use App\Models\Enrollment;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

it('deletes a delivery option without enrollments or order history', function (): void {
    Event::fake([ProductCacheInvalidated::class]);
    $option = ProductDeliveryOption::factory()->create();

    app(DeleteProductDeliveryOptionAction::class)->handle($option);

    expect(ProductDeliveryOption::query()->whereKey($option->id)->exists())->toBeFalse();
    Event::assertDispatched(ProductCacheInvalidated::class);
});

it('blocks deletion when the delivery option has enrollments', function (): void {
    $option = ProductDeliveryOption::factory()->create();
    Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    expect(fn () => app(DeleteProductDeliveryOptionAction::class)->handle($option))
        ->toThrow(
            ValidationException::class,
            __('validation.custom.product_delivery_option.cannot_delete_delivery_option_with_orders')
        );

    expect(ProductDeliveryOption::query()->whereKey($option->id)->exists())->toBeTrue();
});

it('blocks deletion when the delivery option has order history', function (): void {
    $option = ProductDeliveryOption::factory()->create();
    OrderItem::factory()->create(['product_delivery_option_id' => $option->id]);

    expect(fn () => app(DeleteProductDeliveryOptionAction::class)->handle($option))
        ->toThrow(
            ValidationException::class,
            __('validation.custom.product_delivery_option.cannot_delete_delivery_option_with_orders')
        );

    expect(ProductDeliveryOption::query()->whereKey($option->id)->exists())->toBeTrue();
});
