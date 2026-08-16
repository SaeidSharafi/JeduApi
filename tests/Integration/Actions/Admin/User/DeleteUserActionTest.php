<?php

declare(strict_types=1);

use App\Actions\Admin\User\DeleteUserAction;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

/**
 * Assert the action throws with the expected blocking model.
 */
function expectDeleteUserBlockedWithModel(Closure $callable, string $model): void
{
    $caught = null;
    try {
        $callable();
    } catch (ModelHasRelationshipDataException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->getRelatedModel())->toBe($model);
}

it('deletes a user and their wallet when no related data exists', function (): void {
    $user     = User::factory()->create();
    $walletId = $user->wallet->id;

    app(DeleteUserAction::class)->handle($user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    expect(Wallet::query()->whereKey($walletId)->exists())->toBeFalse();
});

it('blocks deletion when the user has teacher data', function (): void {
    $user = User::factory()->create();
    Teacher::factory()->create(['user_id' => $user->id]);

    expectDeleteUserBlockedWithModel(fn () => app(DeleteUserAction::class)->handle($user), Teacher::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('blocks deletion when the user has orders', function (): void {
    $user = User::factory()->create();
    Order::factory()->create(['customer_id' => $user->id]);

    expectDeleteUserBlockedWithModel(fn () => app(DeleteUserAction::class)->handle($user), Order::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('blocks deletion when the user has enrollments', function (): void {
    $user = User::factory()->create();
    Enrollment::factory()->create(['customer_id' => $user->id]);

    expectDeleteUserBlockedWithModel(fn () => app(DeleteUserAction::class)->handle($user), Enrollment::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('blocks deletion when the user has payments', function (): void {
    $user = User::factory()->create();
    Payment::factory()->topup()->create(['customer_id' => $user->id]);

    expectDeleteUserBlockedWithModel(fn () => app(DeleteUserAction::class)->handle($user), Payment::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('blocks deletion when the user has wallet transactions', function (): void {
    $user = User::factory()->create();
    WalletTransaction::factory()->forWallet($user->wallet)->create();

    expectDeleteUserBlockedWithModel(fn () => app(DeleteUserAction::class)->handle($user), WalletTransaction::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('revokes sanctum tokens when deleting a user', function (): void {
    $user = User::factory()->create();
    $user->createToken('test-token');

    app(DeleteUserAction::class)->handle($user);

    expect(DB::table('personal_access_tokens')
        ->where('tokenable_id', $user->id)
        ->where('tokenable_type', User::class)
        ->exists())->toBeFalse();
});

it('detaches media when deleting a user', function (): void {
    $this->fakeMedia();
    $user  = User::factory()->create();
    $media = Media::query()->where('directory', 'fake-media')->first();
    $user->attachMedia($media, 'avatar');

    app(DeleteUserAction::class)->handle($user);

    expect(DB::table('mediables')
        ->where('mediable_id', $user->id)
        ->where('mediable_type', User::class)
        ->exists())->toBeFalse();
});
