<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Teacher;
use App\Models\User;
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
            $user->delete();
        });
    }
}
