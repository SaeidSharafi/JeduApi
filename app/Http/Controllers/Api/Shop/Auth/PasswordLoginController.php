<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Auth;

use App\Actions\Auth\PasswordLoginAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Customer\CustomerData;
use App\Exceptions\UserBannedException;
use App\Exceptions\UserNotFoundException;
use App\Helpers\PhoneNumberHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @group Shop - Auth
 */
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
     * @responseFile 200 resources/responses/shop/auth/login.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(LoginRequest $request): ApiResponseInterface
    {
        $type = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::when(
            $type === 'email',
            fn (Builder $q) => $q->where('email', $request->identifier),
            fn (Builder $q) => $q->whereIn('phone', PhoneNumberHelper::lookupVariants($request->identifier))
        )->first();

        try {
            $token = $this->action->execute(
                $user,
                $type,
                $request->password
            );
            cookie()->queue(
                'user_token',
                $token->plainTextToken,
                (int) config('sanctum.expiration'),
                '/',
                null,
                app()->isProduction() || (bool) config('session.secure'),
                true,
                false,
                'Lax'
            );

            return apiResponse()->success([
                'token'      => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'type'       => 'Bearer',
                'user'       => CustomerData::from($user),
            ], __('messages.auth.login.success'));
        } catch (UserNotFoundException $exception) {
            return apiResponse()->notFound(
                message: __('messages.auth.login.not_found')
            );
        } catch (UserBannedException) {
            return apiResponse()->forbidden(
                message: __('messages.auth.login.banned')
            );
        }

    }
}
