<?php

declare(strict_types=1);

use App\Models\Staff;

it('shows staff profile using the http only authentication cookie', function (): void {
    $staff = Staff::factory()->create();
    $token = $staff->createToken('staff_token')->plainTextToken;

    $this->withCredentials()
        ->withCookie('staff_token', $token)
        ->getJson(route('api.v1.admin.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $staff->id);
});

it('rejects unauthenticated staff profile requests', function (): void {
    $this->getJson(route('api.v1.admin.profile.show'))
        ->assertUnauthorized();
});
