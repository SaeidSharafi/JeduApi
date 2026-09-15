<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\UserSelectOptionData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
final class CustomerSelectOptionController extends Controller
{
    /**
     * Customer list
     *
     * @queryParam  q string The search query for filtering customers (match combined [first_name and last_name],
     *              email and phone and civil id. Example: "John Doe"
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/customer.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $queryString = request()->string('q', '');
        $perPage     = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

        $customers = User::query()
            ->when($queryString, function ($query) use ($queryString): void {
                $query->where(function ($customer) use ($queryString): void {
                    $customer->whereLike(DB::raw("CONCAT(first_name, ' ', last_name)"), '%'.$queryString.'%')
                        ->orWhereLike('civil_id', '%'.$queryString.'%')
                        ->orWhereLike('email', '%'.$queryString.'%')
                        ->orWhereLike('phone', '%'.$queryString.'%');
                });
            })
            ->orderBy('last_name')
            ->paginate($perPage, ['id', 'first_name', 'last_name', 'email', 'phone', 'avatar_url'])
            ->withQueryString();

        return apiResponse()->success(
            UserSelectOptionData::collect($customers)
        );
    }
}
