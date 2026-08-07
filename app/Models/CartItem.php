<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_delivery_option_id',
        'payment_type',
        'quantity',
    ];

    /**
     * Get the cart that owns the cart item.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the product delivery option for the cart item.
     *
     * @return BelongsTo<ProductDeliveryOption, $this>
     */
    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class);
    }

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'payment_type' => OrderItemPaymentTypeEnum::class,
        ];
    }
}
