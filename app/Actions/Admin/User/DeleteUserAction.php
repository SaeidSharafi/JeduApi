<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DeleteUserAction
{
    /**
     * Execute the action.
     *
     * @throws ModelHasRelationshipDataException|Throwable
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if ($user->teacherData()->exists()) {
                throw new ModelHasRelationshipDataException(relatedModel: Teacher::class);
            }

            if ($user->orders()->exists()) {
                throw new ModelHasRelationshipDataException(relatedModel: Order::class);
            }

            if ($user->enrollments()->exists()) {
                throw new ModelHasRelationshipDataException(relatedModel: Enrollment::class);
            }

            if ($user->payments()->exists()) {
                throw new ModelHasRelationshipDataException(relatedModel: Payment::class);
            }

            if ($user->wallet?->transactions()->exists() === true) {
                throw new ModelHasRelationshipDataException(relatedModel: WalletTransaction::class);
            }

            // Mediable pivot rows and Sanctum tokens have no DB-level cascade and
            // would otherwise linger after the user is gone.
            $user->media()->detach();
            $user->tokens()->delete();
            $user->wallet?->delete();
            $user->delete();
        });
    }
}
