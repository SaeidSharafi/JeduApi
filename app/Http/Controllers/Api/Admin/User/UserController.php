<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\User;

use App\Actions\Admin\User\CreateUserAction;
use App\Actions\Admin\User\DeleteUserAction;
use App\Actions\Admin\User\UpdateUserAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\User\ShowUserData;
use App\Data\Admin\User\UserCreateData;
use App\Data\Admin\User\UserListItemData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * @group Admin - User Management
 *
 * APIs for managing users in the system.
 *
 * @authenticated Staff
 */
final class UserController extends Controller
{
    /**
     * Display a listing of the Users.
     *
     * @queryParam filter[name] string Filter by user name. Example: John Doe
     * @queryParam filter[email] string Filter by user email. Example: user@example.com
     * @queryParam filter[phone] string Filter by user phone number. Example: 09301234567
     * @queryParam filter[civil_id] string Filter by user civil ID. Example: 123456789
     * @queryParam filter[civil_id_type] string Filter by user civil ID type. Example: national_id
     * @queryParam filter[date_of_birth_from] string Filter by user date of birth from. Example: 1400-01-01
     * @queryParam filter[date_of_birth_to] string Filter by user date of birth to. Example: 1400-12-29
     * @queryParam sort string Sort by a field. Allowed values: first_name, last_name, email, phone, civil_id,
     *     civil_id_type, date_of_birth. Prefix with '-' for descending order
     *
     * @responseFile 200 responses/user/index.json
     * @responseFile 403 responses/403.json
     */
    public function index()
    {
        Gate::authorize('viewAny', User::class);
        $user = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::callback('name', function ($query, $value): void {
                    $query->whereRaw("concat(first_name, ' ', last_name) like ?", '%'.$value.'%');
                }),
                'email',
                'phone',
                'civil_id',
                AllowedFilter::exact('civil_id_type'),
                AllowedFilter::callback('date_of_birth_from',
                    function (Builder $query, $value): void {
                        $query->whereJalaiDate('date_of_birth', '>=', $value);
                    },
                ),
                AllowedFilter::callback('date_of_birth_to',
                    function (Builder $query, $value): void {
                        $query->whereJalaiDate('date_of_birth', '<=', $value);
                    },
                ),
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

    /**
     * Store a newly created User in storage.
     *
     * @responseFile 201 responses/user/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 422 responses/422.json
     */
    public function store(UserCreateData $data, CreateUserAction $action)
    {
        Gate::authorize('create', User::class);
        $user = $action->handle($data);

        return response()->created(ShowUserData::from($user), model: User::class);
    }

    /**
     * Display the specified User.
     *
     * @responseFile 200 responses/user/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     * */
    public function show(User $user)
    {
        Gate::authorize('view', $user);

        return response()->success(ShowUserData::from($user));
    }

    /**
     * Update the specified User in storage.
     *
     * @responseFile 200 responses/user/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(UserCreateData $data, User $user, UpdateUserAction $action)
    {
        Gate::authorize('update', $user);
        $user = $action->handle($data, $user);

        return response()->updated(ShowUserData::from($user), model: User::class);
    }

    /**
     * Remove the specified User from storage.
     *
     * @response 204
     *
     * @responseFile 422 responses/422-delete.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     *
     * @throws ModelHasRelationshipDataException|Throwable
     */
    public function destroy(User $user, DeleteUserAction $action): JsonResponse|ApiResponseInterface
    {
        Gate::authorize('delete', $user);
        try {
            $action->handle($user);
        } catch (ModelHasRelationshipDataException $exception) {
            return response()->validationError(
                message: __(
                    'messages.errors.model_has_relationship_data',
                    [
                        'model'         => __('messages.models.user'),
                        'related_model' => getModelLabel($exception->getRelatedModel()),
                    ]
                )
            );
        }

        return response()->noContentJson();
    }
}
