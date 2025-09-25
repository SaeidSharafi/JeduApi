<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings\Slider;

use App\Actions\Admin\Slider\CreateSliderAction;
use App\Actions\Admin\Slider\DeleteSliderAction;
use App\Actions\Admin\Slider\UpdateSliderAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Slider\SliderCreateData;
use App\Data\Admin\Slider\SliderData;
use App\Data\Admin\Slider\SliderListItemData;
use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Settings - Slider
 *
 * @authenticated
 *
 * APIs for managing sliders
 */
final class SliderController extends Controller
{
    /**
     * @return ApiResponseInterface
     *                              Display a listing of the resource.
     *
     * @queryParam filter[title] string Filter by title Example: Welcome
     * @queryParam sort string Sort by title,order,created_at Example: -order
     * @queryParam page int Page number Example: 1
     * @queryParam per_page int Items per page Example: 15
     *
     * @responseFile 200 scenario="success" responses/settings/slider/index.json
     * @responseFile 403 responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Slider::class);
        $sliders = QueryBuilder::for(Slider::class)
            ->defaultSort('order')
            ->allowedFilters([
                'title',
            ])
            ->allowedSorts('order', 'title', 'created_at')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(SliderListItemData::collect($sliders));
    }

    /**
     * @responseFile 200 scenario="success" responses/settings/slider/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(Slider $slider): ApiResponseInterface
    {
        Gate::authorize('view', $slider);
        $slider->load('media');

        return response()->success(SliderData::from([
            ...$slider->toArray(),
            'image' => $slider->getImage(),
        ]));
    }

    /**
     * @responseFile 201 scenario="success" responses/settings/slider/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 422 responses/422.json
     */
    public function store(SliderCreateData $data, CreateSliderAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Slider::class);
        $slider = $action->handle($data);
        $slider->load('media');

        return response()->created(SliderData::from([
            ...$slider->toArray(),
            'image' => $slider->getImage(),
        ]));
    }

    /**
     * @responseFile 200 scenario="success" responses/settings/slider/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(SliderCreateData $data, Slider $slider, UpdateSliderAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $slider);
        $slider = $action->handle($slider, $data);
        $slider->load('media');

        return response()->updated(SliderData::from([
            ...$slider->toArray(),
            'image' => $slider->getImage(),
        ]), model: Slider::class);
    }

    /**
     * @response 204
     *
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function destroy(Slider $slider, DeleteSliderAction $action): JsonResponse
    {
        Gate::authorize('delete', $slider);
        $action->handle($slider);

        return response()->noContentJson();
    }
}
