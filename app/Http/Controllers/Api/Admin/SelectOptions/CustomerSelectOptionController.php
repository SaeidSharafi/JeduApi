<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\UserSelectOptionData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
     *
     * @responseFile 200 resources/responses/admin/select-options/customer.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $queryString = request()->string('q', '');
        $limit       = request()->integer('limit', 10);

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
            ->when($limit, fn (Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'avatar_url']);

        return apiResponse()->success(
            UserSelectOptionData::collect($customers)
        );
    }
}
