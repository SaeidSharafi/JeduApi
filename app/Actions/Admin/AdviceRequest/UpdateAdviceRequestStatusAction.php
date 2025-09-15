<?php

namespace App\Actions\Admin\AdviceRequest;

use App\Data\Admin\AdviceRequest\AdviceRequestUpdateData;
use App\Models\AdviceRequest;
use App\Models\Staff;

class UpdateAdviceRequestStatusAction
{
    public function handle(AdviceRequestUpdateData $data, AdviceRequest $adviceRequest, Staff $staff): AdviceRequest
    {
        $adviceRequest->update(
            [
                'status' => $data->status,
                'handled_by_id' => $staff->id,
            ]
        );
        return $adviceRequest;
    }
}
