<?php

use App\Models\Staff;
use App\Models\User;
use \Illuminate\Support\Facades\Hash;
use function Pest\Laravel\putJson;

it('change customer password', function (): void {
    $user = User::factory()->create(
        [
            'password' => null
        ]
    );
    $this->customer($user);
    $response = putJson(route('api.v1.shop.customer.change-password'), [
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertSuccessful();

    expect(Hash::check('newpassword', $user->fresh()->password))->toBeTrue();
});
it('change staff password', function (): void {
    $this->user = Staff::factory()->create(
        [
            'password' => null
        ]
    );
    $this->admin_user();
    $response = putJson(route('api.v1.admin.change-password'), [
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertSuccessful();

    expect(Hash::check('newpassword', $this->user->fresh()->password))->toBeTrue();
});
