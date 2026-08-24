<?php

declare(strict_types=1);

use App\Data\Shop\Customer\CustomerData;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

it('reports is_teacher false for a customer without a teacher record', function (): void {
    $user = User::factory()->create();

    $this->customer($user)
        ->getJson(route('api.v1.shop.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.is_teacher', false);
});

it('reports is_teacher true when a teacher record is linked to the customer', function (): void {
    $user = User::factory()->create();
    Teacher::factory()->create(['user_id' => $user->id]);

    $this->customer($user)
        ->getJson(route('api.v1.shop.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.is_teacher', true);
});

it('does not grant teacher status when the teacher record belongs to another customer', function (): void {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();
    Teacher::factory()->create(['user_id' => $otherUser->id]);

    $this->customer($user)
        ->getJson(route('api.v1.shop.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.is_teacher', false);
});

it('returns is_teacher in the password login response', function (): void {
    $user = User::factory()->create([
        'email'    => 'teacher@example.com',
        'password' => Hash::make('password-123'),
    ]);
    Teacher::factory()->create(['user_id' => $user->id]);

    $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'teacher@example.com',
        'type'       => 'email',
        'password'   => 'password-123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.is_teacher', true);
});

it('does not run additional teacher queries when mapping a collection with teacherData eager loaded', function (): void {
    foreach (range(1, 3) as $i) {
        $user = User::factory()->create();
        Teacher::factory()->create(['user_id' => $user->id]);
    }

    $loaded = User::with('teacherData')->get();

    DB::enableQueryLog();
    $loaded->map(fn (User $user): CustomerData => CustomerData::fromUser($user));
    DB::disableQueryLog();

    $teacherQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'teachers'));

    expect($teacherQueries)->toHaveCount(0);
});

it('blows up loudly when is_teacher is accessed without loading teacherData on a collection', function (): void {
    $a = User::factory()->create();
    $b = User::factory()->create();

    // Laravel only enables the lazy-loading violation guard on multi-model hydration.
    User::whereIn('id', [$a->id, $b->id])->get()->first()->is_teacher;
})->throws(LazyLoadingViolationException::class);
