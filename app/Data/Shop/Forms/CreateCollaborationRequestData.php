<?php

declare(strict_types=1);

namespace App\Data\Shop\Forms;

use App\Rules\IranMobilePhoneRule;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateCollaborationRequestData extends Data
{
    public function __construct(
        public string $full_name,
        public string $phone,
        public string $email,
        public ?string $department,
        public string $message,
        public ?UploadedFile $attachment,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $maxFileSize = config('mediable.customer_max_size') / 1024; // in KB
        return [
            'full_name'  => ['required', 'string', 'max:255'],
            'phone'      => ['required', new IranMobilePhoneRule()],
            'email'      => ['required', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'message'    => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:'.$maxFileSize], // max 5MB
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     */
    public static function bodyParameters(): array
    {
        return [
            'full_name' => [
                'description' => 'Full name of the person making the request',
                'example'     => 'John Doe',
            ],
            'phone' => [
                'description' => 'Phone number of the person making the request',
                'example'     => '+1234567890',
            ],
            'email' => [
                'description' => 'Email address of the person making the request',
                'example'     => 'john_doe@example.com',
            ],
            'department' => [
                'description' => 'Department the person wants to collaborate with',
                'example'     => 'Sales',
            ],
            'message' => [
                'description' => 'Message from the person making the request',
                'example'     => 'I am interested in collaborating with your company.',
            ],
            'attachment' => [
                'description' => 'Optional attachment file (pdf, doc, docx, jpg, png)',
                'example'     => null,
            ],
        ];
    }
}
