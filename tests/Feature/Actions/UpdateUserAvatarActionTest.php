<?php

declare(strict_types=1);

use App\Actions\User\UpdateUserAvatarAction;
use App\Enums\PermissionEnum;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

mutates(UpdateUserAvatarAction::class, PersonalAccessToken::class);

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

describe('Customer avatar API', function (): void {
    it('stores the uploaded customer avatar and refreshes the authenticated tokenable', function (): void {
        Storage::fake('public');

        $user       = User::factory()->create(['avatar_url' => null]);
        $plainToken = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
        $token      = PersonalAccessToken::findToken($plainToken);
        $token->tokenable;

        $response = $this->withToken($plainToken)
            ->post(route('api.v1.shop.profile.avatar.update'), [
                'file' => UploadedFile::fake()->image('customer-avatar.png'),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.avatar_url', $user->fresh()->avatar_url);

        $updatedUser = $user->fresh();
        $avatar      = $updatedUser->firstMedia('avatar');

        expect($updatedUser->avatar_url)->not->toBeNull()
            ->and($avatar)->not->toBeNull()
            ->and(PersonalAccessToken::findToken($plainToken)->tokenable->avatar_url)->toBe($updatedUser->avatar_url);
        Storage::disk('public')->assertExists($avatar->getDiskPath());
    });

    it('removes customer avatar media and clears the authenticated tokenable when deleted', function (): void {
        Storage::fake('public');

        $user = User::factory()->create();
        app(UpdateUserAvatarAction::class)->handle(
            $user,
            UploadedFile::fake()->image('customer-avatar.png'),
        );
        $plainToken = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
        $token      = PersonalAccessToken::findToken($plainToken);
        $token->tokenable;
        $avatar = $user->fresh()->firstMedia('avatar');

        $this->withToken($plainToken)
            ->deleteJson(route('api.v1.shop.profile.avatar.destroy'))
            ->assertNoContent();

        expect($user->fresh()->avatar_url)->toBeNull()
            ->and($user->fresh()->getMedia('avatar'))->toBeEmpty()
            ->and(PersonalAccessToken::findToken($plainToken)->tokenable->avatar_url)->toBeNull();
        Storage::disk('public')->assertMissing($avatar->getDiskPath());
    });
});

describe('Admin avatar API', function (): void {
    it('replaces the selected customer avatar and returns the new media to authorized staff', function (): void {
        Storage::fake('public');
        $this->authorized_user([PermissionEnum::USER_UPDATE]);
        $target        = User::factory()->create();
        $otherCustomer = User::factory()->create();

        app(UpdateUserAvatarAction::class)->handle(
            $target,
            UploadedFile::fake()->image('old-avatar.png'),
        );
        app(UpdateUserAvatarAction::class)->handle(
            $otherCustomer,
            UploadedFile::fake()->image('other-avatar.png'),
        );
        $oldAvatar   = $target->fresh()->firstMedia('avatar');
        $otherAvatar = $otherCustomer->fresh()->firstMedia('avatar');

        $response = $this->post(route('api.v1.admin.users.avatar', $target), [
            'file' => UploadedFile::fake()->image('new-avatar.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.media.avatar.0.url', $target->fresh()->avatar_url);

        $updatedTarget = $target->fresh();
        $newAvatar     = $updatedTarget->firstMedia('avatar');

        expect($newAvatar)->not->toBeNull()
            ->and($newAvatar->id)->not->toBe($oldAvatar->id)
            ->and($updatedTarget->getMedia('avatar'))->toHaveCount(1)
            ->and($otherCustomer->fresh()->firstMedia('avatar')->id)->toBe($otherAvatar->id);
        Storage::disk('public')->assertExists($newAvatar->getDiskPath());
    });

    it('rejects staff without user update permission without changing the customer avatar', function (): void {
        Storage::fake('public');
        $this->unauthorized_user();
        $target = User::factory()->create();
        app(UpdateUserAvatarAction::class)->handle(
            $target,
            UploadedFile::fake()->image('existing-avatar.png'),
        );
        $avatarUrl = $target->fresh()->avatar_url;
        $avatarId  = $target->fresh()->firstMedia('avatar')->id;

        $this->post(route('api.v1.admin.users.avatar', $target), [
            'file' => UploadedFile::fake()->image('rejected-avatar.png'),
        ])->assertForbidden();

        expect($target->fresh()->avatar_url)->toBe($avatarUrl)
            ->and($target->fresh()->firstMedia('avatar')->id)->toBe($avatarId);
    });
});
