<?php

declare(strict_types=1);

namespace App\Actions\Admin\Category;

use App\Data\Admin\Category\CreateCategoryData;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCategoryAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateCategoryData $data, Category $category): void
    {
        DB::transaction(function () use ($category, $data): void {
            $media = $data->media ?? [];
            $category->fill($data->except('media')->toArray());
            if ($media['image'] ?? null) {
                $category->syncMedia(data_get($media, 'image'), 'image');
                $category->image_url = $category->getMedia('image')->first()->getUrl();
            }
            if ($media['icon'] ?? null) {
                $category->syncMedia(data_get($media, 'icon'), 'icon');
                $category->icon_url = $category->getMedia('icon')->first()->getUrl();
            }
            if ($media['educational_calendar'] ?? null) {
                $category->syncMedia(data_get($media, 'educational_calendar'), 'educational_calendar');
                $category->educational_calendar_url = $category->getMedia('educational_calendar')->first()->getUrl();
            }
            $category->save();
        });
    }
}
