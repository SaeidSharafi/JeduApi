<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\PasswordLoginAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\User\ShowUserData;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PasswordLoginController extends Controller
{
    public function __construct(
        protected PasswordLoginAction $action
    ) {
    }

    /**
     * Authenticate User (Customer) with identifier (phone/email) and password
     *
     * Used when the /auth/initiate step determines a password exists and is required.
     *
     *
     * @throws UserNotFoundException
     *
     * @group User Authentication
     *
     * @response {
     *      "message": "User Logged in successfully",
     *      "data": {
     *          "token": "8|juaIqinWuRHiE2vnr3TGr7Pjuy04oHFFilPXxd2Y26f5f131",
     *          "expires_at": null,
     *          "type": "Bearer",
     *          "user": {
     *             "id": 1,
     *             "phone": "09351234567",
     *             "email": "customer@example.com",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "phone2": null,
     *            "civil_id": "4310215648",
     *             "civil_id_type": "national_id",
     *            "date_of_birth": "1380-01-06",
     *            "father_name": "Ali",
     *            "gender" : "male",
     *            "education_level": "bachelor",
     *           "field_of_study": "Computer Science",
     *           "education_status": "graduated",
     *          }
     *      },
     *      "metadata": []
     * }
     * @response 404 {
     *      "message": "User not found",
     *      "errors": null,
     *      "metadata": []
     * }
     * @response 422{
     *      "message": "The provided credentials are incorrect.",
     *      "errors": {
     *      "password": [
     *          "The provided credentials are incorrect."
     *          ]
     *      },
     *      "metadata": []
     * }
     */
    public function __invoke(LoginRequest $request): ApiResponseInterface
    {
        $type = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::when(
            $type === 'email',
            fn(Builder $q) => $q->where('email', $request->identifier),
            fn(Builder $q) => $q->where('phone', $request->identifier)
        )->first();

        try {
            $token = $this->action->execute(
                $user,
                $type,
                $request->password
            );

            return response()->success([
                'token'      => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'type'       => 'Bearer',
                'user'       => ShowUserData::from($user),
            ], 'User Logged in successfully');
        } catch (UserNotFoundException $exception) {
            return response()->notFound(
                message: 'User not found'
            );
        }

    }
}
