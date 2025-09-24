<?php

declare(strict_types=1);

namespace App\Actions\Admin\AdviceRequest;

use App\Data\Admin\AdviceRequest\AdviceRequestUpdateData;
use App\Models\AdviceRequest;
use App\Models\Staff;

final class UpdateAdviceRequestStatusAction
{
    public function handle(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, Staff $staff): AdviceRequest
    {
        $adviceRequest->update(
            [
                'status'        => $data->status,
                'handled_by_id' => $staff->id,
            ]
        );

        return $adviceRequest;
    }
}
