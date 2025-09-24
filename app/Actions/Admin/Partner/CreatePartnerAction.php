<?php

declare(strict_types=1);

namespace App\Actions\Admin\Partner;

use App\Data\Admin\Partner\PartnerCreateData;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class CreatePartnerAction
{
    public function handle(PartnerCreateData $data): Partner
    {
        return DB::transaction(function () use ($data): Partner {
            $image = null;
            if ($data->image) {
                $image = Media::find($data->image);
            }
            $partnerData = [
                ...$data->except('image')->toArray(),
                'image_url' => $image?->getUrl(),
                'image_alt' => $data->title,
            ];
            $partner = Partner::query()->create($partnerData)->fresh();
            $partner->syncMedia($image, 'image');
            $partner->refresh();

            return $partner;
        });
    }
}
