<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Setting\StoreHomePageBlockAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\HomePageBlock\HomePageBlockCreateData;
use App\Data\Admin\Settings\HomePageBlock\HomePageBlockData;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin - Settings - Home Page Blocks
 *
 * @authenticated
 *
 * APIs for managing home page blocks
 */
class HomePageBlockController
{
    /**
     * Store a newly created home page block in storage.
     *
     */
    public function store(
        HomePageBlockCreateData $data,
        StoreHomePageBlockAction $action
    ): ApiResponseInterface {
        $block = $action->handle($data);

        $responseDto = HomePageBlockData::fromModel($block);
        return response()->created($responseDto);
    }
}
