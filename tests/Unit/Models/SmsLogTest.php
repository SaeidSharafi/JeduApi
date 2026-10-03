<?php

declare(strict_types=1);

it('to array', function (): void {
    $smsLog = App\Models\SmsLog::create([
        'status'  => 200,
        'data'    => ['message_id' => '1234567890'],
        'content' => 'Test message content',
        'type'    => 'custom',
        'to'      => ['+1234567890'],
        'from'    => '+0987654321',
        'sent_at' => now(),
    ])->fresh();

    expect($smsLog->toArray())
        ->toEqual([
            'id'         => $smsLog->id,
            'status'     => $smsLog->status,
            'data'       => $smsLog->data,
            'content'    => $smsLog->content,
            'type'       => $smsLog->type,
            'to'         => $smsLog->to,
            'from'       => $smsLog->from,
            'sent_at'    => $smsLog->sent_at?->utc()->toJSON(),
            'created_at' => $smsLog->created_at?->utc()->toJSON(),
            'updated_at' => $smsLog->updated_at?->utc()->toJSON(),
        ]);
});
