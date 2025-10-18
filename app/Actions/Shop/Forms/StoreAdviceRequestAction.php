<?php

declare(strict_types=1);

namespace App\Actions\Shop\Forms;

use App\Data\Shop\Forms\AdviceRequestCreateData;
use App\Models\AdviceRequest;

final class StoreAdviceRequestAction
{
    public function handle(AdviceRequestCreateData $data): void
    {
        AdviceRequest::create([
            'phone' => $data->phone,
        ]);
    }
}
