<?php

declare(strict_types=1);

namespace App\Data\Shop\Forms;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ContactUsRequestData extends Data
{
    public function __construct(
        public string $full_name,
        public string $phone,
        public string $subject,
        public string $email,
        public string $message,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['required', 'string', 'max:30'],
            'subject'   => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255'],
            'message'   => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'full_name' => [
                'description' => 'Full name of the user.',
                'example' => 'John Doe',
            ],
            'phone' => [
                'description' => 'Phone number of the user.',
                'example' => '+1234567890',
            ],
            'subject' => [
                'description' => 'Subject of the message.',
                'example' => 'Inquiry about courses',
            ],
            'email' => [
                'description' => 'Email address of the user.',
                'example' => 'john@example.com',
            ],
            'message' => [
                'description' => 'Message content.',
                'example' => 'I would like to know more about your courses.',
            ],
        ];
    }
}
