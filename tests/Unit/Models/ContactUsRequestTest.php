<?php

declare(strict_types=1);

it('to array', function (): void {
    $contactUsRequest = App\Models\ContactUsRequest::factory()->create([
        'full_name' => 'John Doe',
        'phone'     => '1234567890',
        'subject'   => 'Test Subject',
        'email'     => 'johndoe@example.com',
        'message'   => 'This is a test message.',
    ])->fresh();

    $array = $contactUsRequest->toArray();
    expect($array)->toHaveKeys([
        'id',
        'full_name',
        'phone',
        'subject',
        'email',
        'message',
        'created_at',
        'updated_at',
    ])
        ->and($array['full_name'])->toBe('John Doe')
        ->and($array['phone'])->toBe('1234567890')
        ->and($array['subject'])->toBe('Test Subject')
        ->and($array['email'])->toBe('johndoe@example.com')
        ->and($array['message'])->toBe('This is a test message.');
});
