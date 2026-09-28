<?php

declare(strict_types=1);

use App\Actions\User\UpdateUserAvatarAction;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

covers(UpdateUserAvatarAction::class, PersonalAccessToken::class);

it('invalidates the cached tokenable after updating a customer avatar', function (): void {
    Storage::fake('public');
    Queue::fake();

    $user  = User::factory()->create(['avatar_url' => null]);
    $plain = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;

    expect(PersonalAccessToken::findToken($plain)->tokenable->avatar_url)->toBeNull();

    app(UpdateUserAvatarAction::class)->handle(
        $user->fresh(),
        UploadedFile::fake()->image('avatar.png'),
    );

    $updatedUser = $user->fresh();

    expect(PersonalAccessToken::findToken($plain)->tokenable->avatar_url)
        ->toBe($updatedUser->avatar_url)
        ->not->toBeNull();
});
