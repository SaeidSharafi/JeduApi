<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\Staff\StaffSelectOptionData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

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
     *
     * @responseFile 200 responses/select-options/staff.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');
        $limit = request()->integer('limit', 10);

        $staffs = \App\Models\Staff::query()
            ->select(['id', 'name', 'email'])
            ->when($query, function ($staff) use ($query) {
                $staff->where(function ($staff) use ($query) {
                    $staff->whereLike('name', '%'.$query.'%')
                        ->orWhereLike('email', '%'.$query.'%')
                        ->orWhereLike('phone', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->when($limit, fn (Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'name', 'email']);

        return response()->success(
            StaffSelectOptionData::collect($staffs)
        );
    }
}
