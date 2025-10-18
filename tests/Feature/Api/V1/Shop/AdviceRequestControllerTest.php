<?php

declare(strict_types=1);

use App\Models\AdviceRequest;

use function Pest\Laravel\postJson;

describe('AdviceRequestController', function (): void {
    describe('store', function (): void {
        it('should create an advice request with valid phone', function (): void {
            $response = postJson(route('api.v1.shop.advice-requests.store'), [
                'phone' => '+1234567890',
            ]);

            $response->assertCreated();
            $response->assertJsonPath('message', 'Your consultation request has been saved. We will contact you shortly.');
            $this->assertDatabaseHas('advice_requests', [
                'phone' => '+1234567890',
            ]);
        });

        it('should fail with missing phone', function (): void {
            $response = postJson(route('api.v1.shop.advice-requests.store'), []);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['phone']);
        });

        it('should fail with invalid phone format', function (): void {
            $response = postJson(route('api.v1.shop.advice-requests.store'), [
                'phone' => str_repeat('a', 31),
            ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['phone']);
        });

        it('should be rate limited to 10 requests per minute', function (): void {
            for ($i = 0; $i < 10; $i++) {
                $response = postJson(route('api.v1.shop.advice-requests.store'), [
                    'phone' => "+12345678{$i}0",
                ]);
                $response->assertCreated();
            }

            $response = postJson(route('api.v1.shop.advice-requests.store'), [
                'phone' => '+19999999999',
            ]);

            $response->assertTooManyRequests();
        });

        it('should store advice request with initial pending status', function (): void {
            postJson(route('api.v1.shop.advice-requests.store'), [
                'phone' => '+9876543210',
            ]);

            $request = AdviceRequest::where('phone', '+9876543210')->first();
            expect($request)->not->toBeNull();
            expect($request->status->value)->toBe('pending');
        });
    });
});
