<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\UpdateUserAction;
use App\Contracts\ApiResponseInterface;
use App\Data\User\ShowUserData;
use App\Data\User\UserCreateData;
use App\Data\User\UserListItemData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index()
    {
        $user = QueryBuilder::for(User::class)
            ->allowedFilters([
                'first_name',
                'last_name',
                'email',
                'phone',
                'civil_id',
                'civil_id_type',
                'date_of_birth',
            ])
            ->allowedSorts([
                'first_name',
                'last_name',
                'email',
                'phone',
                'civil_id',
                'civil_id_type',
                'date_of_birth',
            ])
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return response()->success(UserListItemData::collect($user));
    }

    public function store(UserCreateData $data, CreateUserAction $action)
    {
        $user = $action->handle($data);

        return response()->created(ShowUserData::from($user), model: User::class);
    }

    public function show(User $user)
    {
        return response()->success(ShowUserData::from($user));
    }

    public function update(UserCreateData $data, User $user, UpdateUserAction $action)
    {
        $user = $action->handle($data, $user);

        return response()->updated(ShowUserData::from($user), model: User::class);
    }

    public function destroy(User $user, DeleteUserAction $action): JsonResponse|ApiResponseInterface
    {
        try {
            $action->handle($user);
        }catch (ModelHasRelationshipDataException $exception){
            return response()->validationError(
                message: __(
                    'messages.errors.model_has_relationship_data',
                    [
                        'model' => __('messages.models.user'),
                        'related_model' => getModelLabel($exception->getRelatedModel()),
                    ]
                )
            );
        }
        return response()->noContentJson();
    }
}
