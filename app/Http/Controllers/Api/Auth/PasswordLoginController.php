<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\PasswordLoginAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Customer\CustomerData;
use App\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PasswordLoginController extends Controller
{
    public function __construct(
        protected PasswordLoginAction $action
    ) {}

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
     *              "uuid": "0197f38e-84a3-70d3-ae33-73b777915eb2",
     *              "phone": "09151235664",
     *              "is_profile_completed": true,
     *              "first_name": "Juvenal",
     *              "last_name": "Murray",
     *              "email": "vschiller@example.com",
     *              "phone2": "09371134162",
     *              "civil_id": "93530102067499",
     *              "civil_id_type": {
     *                  "value": "immigrant_code",
     *                  "label": "کد اتباع"
     *              },
     *              "date_of_birth": "1353-01-16",
     *              "father_name": "Prof. Solon Gutkowski",
     *              "gender": {
     *                  "value": "female",
     *                  "label": "زن"
     *              },
     *              "education_level": {
     *                      "value": "under_diploma",
     *                      "label": "زیردیپلم"
     *                  },
     *              "field_of_study": "هنر",
     *              "education_status": {
     *                  "value": "student",
     *                  "label": "دانشجو"
     *              }
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
            fn (Builder $q) => $q->where('email', $request->identifier),
            fn (Builder $q) => $q->where('phone', $request->identifier)
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
                'user'       => CustomerData::from($user),
            ], __('messages.auth.login.success'));
        } catch (UserNotFoundException $exception) {
            return response()->notFound(
                message: __('messages.auth.login.not_found')
            );
        }

    }
}
