<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Actions\Admin\Category\SetGoodForStartAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Category\SetGoodForStartData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Category Managment
 *
 * @authenticated
 */
final class GoodForStartController extends Controller
{
    /**
     * Set the good_for_start flag for items in a category.
     *
     * only items with course type can be set as good_for_start
     */
    public function set(Category $category, SetGoodForStartData $data, SetGoodForStartAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $category);
        $updated = $action->handle($category, $data);

        return response()->success(__('messages.category.good_for_start.updated', ['count' => $updated]));
    }
}
