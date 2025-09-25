<?php

declare(strict_types=1);

namespace App\Actions\Shop\CMS;

use App\Data\Shop\Forms\ContactUsRequestData;
use App\Models\ContactUsRequest;

final class StoreContactUsRequestAction
{
    public function handle(ContactUsRequestData $data): ContactUsRequest
    {
        return ContactUsRequest::create([
            'full_name' => $data->full_name,
            'phone'     => $data->phone,
            'subject'   => $data->subject,
            'email'     => $data->email,
            'message'   => $data->message,
        ]);
    }
}
