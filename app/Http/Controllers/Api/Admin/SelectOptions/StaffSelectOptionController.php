<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\StaffSelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Select Options
 *
 * retrieve a list of terms for select options
 *
 * @authenticated
 */
final class StaffSelectOptionController extends Controller
{
    /**
     * Staff list
     *
     * @queryParam  q string The search query for filtering staff (match first_name, last_name, phone adn email ). Example: "John Doe"
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/staff.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

        $staffs = \App\Models\Staff::query()
            ->where('is_banned', false)
            ->when($query, function ($staff) use ($query): void {
                $staff->where(function ($staff) use ($query): void {
                    $staff->whereLike('name', '%'.$query.'%')
                        ->orWhereLike('email', '%'.$query.'%')
                        ->orWhereLike('phone', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'email'])
            ->withQueryString();

        return apiResponse()->success(
            StaffSelectOptionData::collect($staffs)
        );
    }
}
