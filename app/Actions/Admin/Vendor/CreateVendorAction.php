<?php

declare(strict_types=1);

namespace App\Actions\Admin\Vendor;

use App\Data\Admin\Vendor\CreateVendorData;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

final readonly class CreateVendorAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateVendorData $data): void
    {
        DB::transaction(function () use ($data): void {
            $media  = $data->media;
            $vendor = Vendor::query()->create($data->except('media')->toArray());

            foreach ($media as $tag => $mediaId) {
                $vendor->attachMedia($mediaId, $tag);
                if ($tag === 'logo') {
                    $vendor->logo_url = $vendor->getMedia('logo')->first()->getUrl();
                }
                if ($tag === 'favicon') {
                    $vendor->favicon_url = $vendor->getMedia('favicon')->first()->getUrl();
                }
            }
            $vendor->save();
        });
    }
}
