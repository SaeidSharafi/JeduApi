<?php

declare(strict_types=1);

namespace App\Actions\Admin\Vendor;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Vendor\CreateVendorData;
use App\Enums\System\CacheKey;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

final readonly class UpdateVendorAction
{
    public function __construct(private CacheStore $cache) {}

    /**
     * Execute the action.
     */
    public function handle(CreateVendorData $data, Vendor $vendor): void
    {
        DB::transaction(function () use ($data, $vendor): void {
            $media = $data->media;
            foreach ($media as $tag => $mediaId) {
                $vendor->syncMedia($mediaId, $tag);
            }
            $vendor->update([
                ...$data->except('media')->toArray(),
                'favicon_url' => $vendor->getMedia('favicon')->first()?->getUrl(),
                'logo_url'    => $vendor->getMedia('logo')->first()?->getUrl(),
            ]);
            $vendor->logo_url    = null;
            $vendor->favicon_url = null;

        });

        foreach (range(1, 20) as $limit) {
            $this->cache->forget(CacheKey::OrganizationPage, ['limit' => $limit]);
        }
    }
}
