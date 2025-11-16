<?php

declare(strict_types=1);

it('to array', function (): void {
    $collaborationRequest = App\Models\CollaborationRequest::factory()->create([
        'full_name' => 'Jane Smith',
        'phone'     => '0987654321',
        'email'     => 'johnsmith@example.com',
        'message'   => 'This is a collaboration request message.',
    ])->fresh();

    $array = $collaborationRequest->toArray();
    expect($array)->toHaveKeys([
        'id',
        'full_name',
        'phone',
        'email',
        'message',
        'created_at',
        'updated_at',
    ])
        ->and($array['full_name'])->toBe('Jane Smith')
        ->and($array['phone'])->toBe('0987654321')
        ->and($array['email'])->toBe('johnsmith@example.com')
        ->and($array['message'])->toBe('This is a collaboration request message.');
});
