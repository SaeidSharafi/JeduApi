<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Contracts\CartIdentifier;
use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use App\Services\Discounts\OrderCalculationService;
use Mockery\MockInterface;

use function Pest\Laravel\assertDatabaseMissing;

describe('CartService', function (): void {

    it('deletes the current cart', function (): void {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $service = resolve(CartService::class);

        $service->deleteCart();

        assertDatabaseMissing('carts', ['id' => $cart->id]);
    });

    it('does not throw when no cart exists', function (): void {
        $user = User::factory()->create();

        $this->mock(CartIdentifier::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userId')
                ->once()
                ->andReturn($user->id);
            $mock->shouldReceive('guestToken')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $service = resolve(CartService::class);

        // Should not throw
        $service->deleteCart();

        $this->assertTrue(true);
    });
});
