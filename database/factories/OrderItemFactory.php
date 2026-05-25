<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $productDeliveryOptionFactory = ProductDeliveryOption::factory();

        return [
            'order_id'                   => Order::factory(),
            'product_delivery_option_id' => $productDeliveryOptionFactory,
            'qty_ordered'                => 1,
            'payment_type'               => $this->faker->randomElement(OrderItemPaymentTypeEnum::getAllValues()),
            'name'                       => fn (array $attributes
            ) => ProductDeliveryOption::find($attributes['product_delivery_option_id'])->name,
            'sku' => fn (array $attributes
            ) => ProductDeliveryOption::find($attributes['product_delivery_option_id'])->sku,
            'product_data_snapshot_json' => fn (array $attributes
            ) => ProductDeliveryOption::find($attributes['product_delivery_option_id'])->product->toArray(),
            'vendor_id'         => Vendor::factory(),
            'price'             => 0,
            'discount_amount'   => 0,
            'tax_amount'        => 0,
            'total'             => 0,
            'prepayment_amount' => 0,
            'total_refunded'    => 0,
            'qty_refunded'      => 0,
            'status'            => $this->faker->randomElement(OrderItemStatusEnum::getAllValues()),
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ];
    }

    public function useExistingRelations(): static
    {
        return $this->state(function (array $attributes) {
            $productDeliveryOption = ProductDeliveryOption::query()
                ->where('status', PublicationStatusEnum::PUBLISHED)
                ->inRandomOrder()->first()             ?? ProductDeliveryOption::factory()->create();
            $vendor = Vendor::inRandomOrder()->first() ?? Vendor::factory()->create();

            return [
                'product_delivery_option_id' => $productDeliveryOption->id,
                'vendor_id'                  => $vendor->id,
                'name'                       => $productDeliveryOption->name,
                'sku'                        => $productDeliveryOption->sku,
                'product_data_snapshot_json' => $productDeliveryOption->product->toArray(),
                'price'                      => $productDeliveryOption->price,
                'status'                     => OrderItemStatusEnum::PENDING,
                'payment_type'               => $productDeliveryOption->is_prepayment_available
                    ? OrderItemPaymentTypeEnum::PRE_PAYMENT
                    : OrderItemPaymentTypeEnum::FULL_PAYMENT,
                'total' => $productDeliveryOption->is_prepayment_available
                    ? $productDeliveryOption->prepayment_amount
                    : $productDeliveryOption->price,
            ];
        });
    }

    /**
     * STATE: Create a corresponding Enrollment for this OrderItem.
     * This is the performant way to create a child record.
     */
    public function withEnrollment(bool $provision = false): self
    {
        if (! $provision) {
            return $this->has(
                Enrollment::factory()->state(function (array $attributes, OrderItem $orderItem) {
                    return [
                        'order_id'                   => $orderItem->order_id,
                        'customer_id'                => $orderItem->order->customer_id,
                        'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                    ];
                }),
                'enrollment'
            );
        }

        return $this->has(
            Enrollment::factory()->state(function (array $attributes, OrderItem $orderItem) {
                $provisionData['providers'] = match ($orderItem->productDeliveryOption->delivery_method) {
                    DeliveryMethodEnum::LMS_MOODLE => [
                        'moodle' => [
                            'status'         => 'success',
                            'provisioned_at' => now()->toDateTimeString(),
                            'data'           => [
                                'moodle_user_id'   => '1',
                                'moodle_user_name' => 'moodle-user',
                                'moodle_course_id' => '1',
                                'login_path'       => '/my',
                                'course_info'      => [
                                    'visible'    => false,
                                    'name'       => 'moodle-course',
                                    'course_url' => 'https://lsm.example.com/course/view.php?id=1',
                                    'completed'  => false,
                                    'activities' => [
                                        [
                                            'url'   => 'https://lsm.example.com/mod/quiz/view.php?id=1',
                                            'cid'   => 1,
                                            'name'  => 'moodle-quiz',
                                            'type'  => 'quiz',
                                            'state' => 0,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER => [
                        'spotplayer' => [
                            'status'         => 'success',
                            'provisioned_at' => now()->toDateTimeString(),
                            'data'           => [
                                'spot_id'      => 'SPOT_ID',
                                'license_key'  => 'LICENSE_KEY',
                                'player_url'   => 'https://app.spotplayer.ir/SPOT_ID/STRING/',
                                'raw_response' => [
                                    '_id' => 'SPOT_ID',
                                    'key' => 'LICENSE_KEY',
                                    'url' => '/SPOT_ID/STRING/',
                                ],
                            ],
                        ],
                    ],
                    DeliveryMethodEnum::LIVE_SESSION_BBB => [
                        'bbb' => [
                            'status'         => 'success',
                            'provisioned_at' => now()->toDateTimeString(),
                            'data'           => [
                                'meeting_id'          => 'MEETING_ID',
                                'auto_create_meeting' => false,
                                'attendee_join_url'   => 'https://lsm.example.com/meeting/attendee/join/',
                            ],
                        ],
                    ],
                };
                if (isset($item->productDeliveryOption->details_json['ims_course_code'])) {
                    $provisionData['providers']['ims'] = [
                        'status'         => 'success',
                        'provisioned_at' => now()->toDateTimeString(),
                        'data'           => [
                            'course_code' => 'IMS_COURSE',
                            'resonse'     => [
                                'status'  => 200,
                                'message' => 'Enrollment successful',
                            ],
                        ],
                    ];
                }

                return [
                    'order_id'                   => $orderItem->order_id,
                    'customer_id'                => $orderItem->order->customer_id,
                    'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                    'provisioning_data'          => $provisionData,
                ];
            }),
            'enrollment'
        );
    }
}
