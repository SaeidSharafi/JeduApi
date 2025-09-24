<?php

declare(strict_types=1);

namespace App\Actions\Admin\AdviceRequest;

use App\Data\Admin\AdviceRequest\AdviceRequestUpdateData;
use App\Models\AdviceRequest;
use App\Models\Staff;

final class UpdateAdviceRequestAction
{
    public function handle(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, Staff $staff): AdviceRequest
    {
        $adviceRequest->update([
            ...$data->toArray(),
            'handled_by_id' => $staff->id,
        ]);

        return $adviceRequest;
    }
}
