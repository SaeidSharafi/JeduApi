<?php

declare(strict_types=1);

namespace App\Actions\Admin\Partner;

use App\Data\Admin\Partner\PartnerCreateData;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class UpdatePartnerAction
{
    public function handle(Partner $partner, PartnerCreateData $data): Partner
    {
        return DB::transaction(function () use ($partner, $data): Partner {
            $image = null;
            if ($data->image) {
                $image = Media::find($data->image);
            }
            $partnerData = [
                ...$data->except('image')->toArray(),
                'image_url' => $image ? $image->getUrl() : null,
                'image_alt' => $image ? $data->title : null,
            ];
            $partner->update($partnerData);
            $partner->syncMedia($image, 'image');
            $partner->refresh();

            return $partner;
        });
    }
}
