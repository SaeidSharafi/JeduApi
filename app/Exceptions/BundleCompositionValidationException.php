<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;

final class BundleCompositionValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $field = 'components',
        public readonly ?string $bundleName = null,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): ApiResponseInterface
    {
        $isAdminRoute = $request->route()?->getName() !== null
            && str_starts_with($request->route()->getName(), 'api.v1.admin.');
        $isStaffRequest = auth('staff')->check();

        if ($isAdminRoute || $isStaffRequest) {
            return apiResponse()->validationErrors([
                $this->field => [$this->getMessage()],
            ]);
        }

        return apiResponse()->error(
            __('messages.product.bundle_customer_unavailable', ['name' => $this->bundleName ?? __('messages.product.bundle_default_name')]),
            422,
        );
    }
}
