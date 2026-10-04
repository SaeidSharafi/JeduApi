<?php

declare(strict_types=1);

namespace App\Actions\Admin\Organization;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Organization\OrganizationPageUpdateData;
use App\Enums\System\CacheKey;
use App\Models\OrganizationPage;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrganizationPageAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(OrganizationPageUpdateData $data): OrganizationPage
    {
        $page = DB::transaction(function () use ($data): OrganizationPage {
            $page = OrganizationPage::query()->singleton()->firstOrFail();

            $page->update($data->except('media')->toArray());

            foreach (['hero', 'educational_calendar'] as $tag) {
                $page->syncMedia($data->media[$tag] ?? null, $tag);
            }

            $page->hero_image_url           = $page->firstMedia('hero')?->getUrl();
            $page->educational_calendar_url = $page->firstMedia('educational_calendar')?->getUrl();
            $page->save();

            return $page->fresh(['media']);
        });

        foreach (range(1, 20) as $limit) {
            $this->cache->forget(CacheKey::OrganizationPage, ['limit' => $limit]);
        }

        return $page;
    }
}
