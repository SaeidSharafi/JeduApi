<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;

/**
 * Raised when a structural parent Bundle PDO reaches a seam that may only
 * consume physical component entitlements (provisioning, join, download,
 * teacher course). The Bundle is a commercial grouping record: it has no
 * learning entitlement of its own, so these seams must reject it explicitly
 * instead of degrading into a provider/not-found error.
 */
final class BundleStructuralInvariantException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('messages.product.bundle_not_directly_provisionable'));
    }

    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->error($this->getMessage(), 422);
    }
}
