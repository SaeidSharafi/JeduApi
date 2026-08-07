<?php

use App\Models\User;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Authentication Scenarios
|--------------------------------------------------------------------------
*/

it('unauthenticated users cannot list payments', function (): void {
    $response = $this->getJson(route('api.v1.shop.student.payments.index'));

    // Assuming your routing or middleware handles unauthorized requests with a 401
    $response->assertStatus(401);
});

it('unauthenticated users cannot view a specific payment', function (): void {
    $payment = Payment::factory()->create();

    $response = $this->getJson(route('api.v1.shop.student.payments.show', $payment->uuid));

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Index Endpoint Scenarios
|--------------------------------------------------------------------------
*/

it('an authenticated user receives an empty list when they have no payments', function (): void {
    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index'));

    $response->assertStatus(200)
        ->assertJsonPath('data.data', []);
});

it('an authenticated user can list only their own payments', function (): void {
    $otherUser = User::factory()->create();

    // Create 3 payments for the authenticated user and 2 for another user
    $userPayments = Payment::factory()->count(3)->create([
        'customer_id' => $this->user->id,
    ]);

    Payment::factory()->count(2)->create([
        'customer_id' => $otherUser->id,
    ]);

    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index'));

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data.data');
});

it('payments are returned with the correct structure and relationships', function (): void {
    $payment = Payment::factory()->create([
        'customer_id' => $this->user->id,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id' => $payment->id,
    ]);

    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
            'data' => [
                '*' => [
                    'uuid',
                    'id',
                    'amount',
                    'method',
                    'status',
                    'purpose',
                    'last_gateway_reference',
                    'attempt_count',
                    'transactions' => [
                        '*' => [
                            'status',
                            'initiated_at',
                            'completed_at',
                        ]
                    ]
                ]
            ]
            ]
        ]);
});

it('payments are sorted from newest to oldest', function (): void {
    $oldPayment = Payment::factory()->create([
        'customer_id' => $this->user->id,
        'created_at' => now()->subDays(2),
    ]);

    $newPayment = Payment::factory()->create([
        'customer_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index'));

    $response->assertStatus(200);

    $data = $response->json('data.data');
    expect($data[0]['uuid'])->toBe($newPayment->uuid);
    expect($data[1]['uuid'])->toBe($oldPayment->uuid);
});

it('it filters payments by valid purpose', function (): void {
    // Collect some backing enum cases for the test
    $cases = PaymentPurposeEnum::cases();

    if (count($cases) < 2) {
        $this->markTestSkipped('At least two PaymentPurposeEnum cases are required for this test.');
    }

    $purposeA = $cases[0];
    $purposeB = $cases[1];

    Payment::factory()->create([
        'customer_id' => $this->user->id,
        'purpose' => $purposeA,
    ]);

    Payment::factory()->create([
        'customer_id' => $this->user->id,
        'purpose' => $purposeB,
    ]);

    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index', ['purpose' => $purposeA->value]));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data');

    expect($response->json('data.data.0.purpose.value'))->toBe($purposeA->value);
});

it('it ignores filtering when the purpose parameter is invalid', function (): void {
    Payment::factory()->count(2)->create([
        'customer_id' => $this->user->id,
    ]);

    // Send an invalid purpose query parameter
    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index', ['purpose' => 'invalid-purpose-string']));

    // Expect no filters to apply, returning all 2 records
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data.data');
});

it('it respects pagination requests using per_page parameter', function (): void {
    Payment::factory()->count(10)->create([
        'customer_id' => $this->user->id,
    ]);

    $response = $this->customer($this->user)
        ->getJson(route('api.v1.shop.student.payments.index', ['per_page' => 3]));

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data.data');
});

/*
|--------------------------------------------------------------------------
| Show Endpoint Scenarios
|--------------------------------------------------------------------------
*/

it('an authenticated user can view their specific payment details', function (): void {
    $payment = Payment::factory()->create([
        'customer_id' => $this->user->id,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id' => $payment->id,
    ]);

    $response = $this->actingAs($this->user, 'user')
        ->getJson(route('api.v1.shop.student.payments.show', $payment->uuid));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'uuid' => $payment->uuid,
                'id' => $payment->id,
                'amount' => $payment->amount,
            ]
        ])
        ->assertJsonStructure([
            'data' => [
                'transactions' => [
                    '*' => ['status', 'initiated_at', 'completed_at']
                ]
            ]
        ]);
});

it('an authenticated user cannot view another users payment', function (): void {
    $otherUser = User::factory()->create();

    $payment = Payment::factory()->create([
        'customer_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user, 'user')
        ->getJson(route('api.v1.shop.student.payments.show', $payment->uuid));

    // Scoped queries with firstOrFail should trigger ModelNotFoundException -> 404
    $response->assertStatus(404);
});

it('viewing a non-existent payment uuid returns 404', function (): void {
    $response = $this->actingAs($this->user, 'user')
        ->getJson(route('api.v1.shop.student.payments.show', \Illuminate\Support\Str::uuid7()));

    $response->assertStatus(404);
});
