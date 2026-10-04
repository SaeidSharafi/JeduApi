<?php

declare(strict_types=1);

namespace App\Data\Shop\Organization;

use App\Rules\IranMobilePhoneRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class OrganizationTrainingRequestCreateData extends Data
{
    /**
     * @param  array<int, string>|null  $requested_course_names
     */
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $phone,
        public string $position,
        public string $organization_name,
        public ?array $requested_course_names = null,
        public ?string $notes = null,
        public ?UploadedFile $attachment = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'first_name'               => ['required', 'string', 'max:100'],
            'last_name'                => ['required', 'string', 'max:100'],
            'phone'                    => ['required', new IranMobilePhoneRule()],
            'position'                 => ['required', 'string', 'max:255'],
            'organization_name'        => ['required', 'string', 'max:255'],
            'requested_course_names'   => ['nullable', 'array', 'max:50'],
            'requested_course_names.*' => ['required', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string', 'max:5000'],
            'attachment'               => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'extensions:pdf',
                'max:2048',
            ],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $courseNames   = $validator->getData()['requested_course_names'] ?? [];
            $hasCourseName = is_array($courseNames)
                && collect($courseNames)->contains(fn (mixed $courseName): bool => is_string($courseName) && mb_trim($courseName) !== '');
            $hasAttachment = ($validator->getData()['attachment'] ?? null) instanceof UploadedFile;

            if (! $hasCourseName && ! $hasAttachment) {
                $validator->errors()->add(
                    'requested_course_names',
                    'At least one requested course name or a PDF attachment is required.',
                );
            }
        });
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public static function bodyParameters(): array
    {
        return [
            'first_name' => [
                'description' => 'The representative first name.',
                'example'     => 'Sara',
            ],
            'last_name' => [
                'description' => 'The representative last name.',
                'example'     => 'Ahmadi',
            ],
            'phone' => [
                'description' => 'The representative mobile phone number.',
                'example'     => '09121234567',
            ],
            'position' => [
                'description' => 'The representative position in the organization.',
                'example'     => 'HR manager',
            ],
            'organization_name' => [
                'description' => 'The organization name.',
                'example'     => 'Example Organization',
            ],
            'requested_course_names' => [
                'description' => 'Manually entered requested course names. Existing course IDs are not accepted.',
                'example'     => ['Project management'],
            ],
            'notes' => [
                'description' => 'Optional additional context.',
                'example'     => 'Please contact us during business hours.',
            ],
            'attachment' => [
                'description' => 'Optional private PDF requirements document, up to 2 MB.',
                'example'     => null,
            ],
        ];
    }
}
