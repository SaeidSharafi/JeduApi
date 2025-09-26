<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Order;
use App\Models\Payment;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

describe('NextPaymentDetailsController', function (): void {

    /**
     * Test the main success case where payment details are returned correctly.
     */
    it('returns payment details successfully for a valid order', function (): void {
        // --- Arrange ---
        // Authorize a user with the correct permission to view orders.
        $this->authorized_user([PermissionEnum::ORDER_VIEW_ANY->value]);

        $product = \App\Models\Product::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $pdo     = \App\Models\ProductDeliveryOption::factory()->create([
            'product_id'      => $product->id,
            'status'          => PublicationStatusEnum::PUBLISHED,
            'delivery_method' => 'direct_download',
            'price'           => 50000,
        ]);
        $items = [
            [
                'product_delivery_option_id' => $pdo->id,
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                'total'                      => $pdo->price,
                'price'                      => $pdo->price,
                'name'                       => 'Workshop',
            ],
        ];
        $order = Order::factory()
            ->withCalculatedTotals($items)
            ->create();
        // --- Act ---
        // Make a GET request to the invokable controller's route.
        $response = $this->getJson(route('api.v1.admin.next-payment-details', ['order' => $order->id]));

        // --- Assert ---
        // Assert a 200 OK status and the correct JSON structure from the NextPaymentDetailsData DTO.
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'amount_due',
                    'payment_type',
                    'summary_description',
                    'line_item_details',
                ],
            ])
            ->assertJsonPath('data.amount_due', 50000);
    });

    /**
     * Test the failure case where the action throws an exception (e.g., for a fully paid order).
     * This specifically tests the `try/catch` block in the controller.
     */
    it('returns a 422 validation error if the action throws an exception', function (): void {
        // --- Arrange ---
        $this->authorized_user([PermissionEnum::ORDER_VIEW_ANY->value]);

        // Create an order that is already fully paid. This will cause the action to throw an Exception.
        $order = Order::factory()->create(['grand_total' => 50000]);
        Payment::factory()->for($order)->create(['amount' => 50000, 'status' => 'completed']);

        // --- Act ---
        $response = $this->getJson(route('api.v1.admin.next-payment-details', ['order' => $order->id]));

        // --- Assert ---
        // Assert a 422 Unprocessable Entity status.
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ])
            // Assert that the exception message is present in the response.
            ->assertJsonPath('errors.0', __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]));
    });

    /**
     * Test the authorization gate.
     */
    it('returns a 403 forbidden error if the user is not authorized', function (): void {
        // --- Arrange ---
        // Use the helper to simulate an authenticated user WITHOUT the required permission.
        $this->unauthorized_user();
        $order = Order::factory()->create();

        // --- Act ---
        $response = $this->getJson(route('api.v1.admin.next-payment-details', ['order' => $order->id]));

        // --- Assert ---
        $response->assertForbidden();
    });

    /**
     * Test Laravel's implicit route model binding for a 404 Not Found error.
     */
    it('returns a 404 not found error for a non-existent order', function (): void {
        // --- Arrange ---
        $this->authorized_user([PermissionEnum::ORDER_VIEW_ANY->value]);

        // --- Act ---
        $response = $this->getJson(route('api.v1.admin.next-payment-details', ['order' => 99999]));

        // --- Assert ---
        $response->assertNotFound();
    });
});
