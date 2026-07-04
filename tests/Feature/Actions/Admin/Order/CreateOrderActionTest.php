<?php

declare(strict_types=1);

use App\Actions\Admin\Order\CreateOrderAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('CreateOrderAction', function (): void {

    it('does not create enrollments', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_CREATE]);

        $user           = User::factory()->create();
        $product        = Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'              => $product->id,
            'status'                  => PublicationStatusEnum::PUBLISHED,
            'price'                   => 50000,
            'is_prepayment_available' => false,
        ]);

        $items = [
            new OrderItemCreateData(
                product_delivery_option_id: $deliveryOption->id,
                payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                qty_ordered: 1,
            ),
        ];

        $data = new OrderCreateData(
            status: OrderStatusEnum::PENDING->value,
            customer_id: $user->id,
            items: $items,
        );

        $action = app(CreateOrderAction::class);
        $order  = $action->handle($data);

        $order->load('items');
        $orderItem = $order->items->first();

        $enrollmentCount = Enrollment::where('order_item_id', $orderItem->id)->count();
        expect($enrollmentCount)->toBe(0);
    });
});
