<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Helpers\JalaliDateHelper;
use App\Rules\ProductableExistRule;
use App\Rules\ValidNormalizedJalaliDateRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductCreateData extends Data
{
    public function __construct(
        public bool $force_create,
        public string $productable_type,
        public int $productable_id,
        public int $vendor_id,
        public int $term_id,
        public string $status,
        public bool $is_visible,
        public ?string $short_description,
        public ?string $short_name,
        public ?string $name,
        public bool $is_featured,
        public array $categories,
        public ?array $details_json,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Carbon $event_start_at = null,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Carbon $event_ended_at = null,
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'event_start_at' => 'Y-m-d H:i:s',
            'event_ended_at' => 'Y-m-d H:i:s',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'force_create'      => ['required', 'boolean'],
            'productable_type'  => ['required', 'string', Rule::enum(ProductableEnum::class)],
            'productable_id'    => ['required', 'integer', new ProductableExistRule()],
            'vendor_id'         => ['required', 'integer', 'exists:vendors,id'],
            'term_id'           => ['required', 'integer', 'exists:terms,id'],
            'status'            => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
            'is_visible'        => ['required', 'boolean'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'short_name'        => ['nullable', 'string', 'max:255'],
            'name'              => ['required', 'string', 'max:255'],
            'is_featured'       => ['required', 'boolean'],
            'categories'        => ['required', 'array'],
            'categories.*'      => ['required', 'integer', 'exists:categories,id'],
            'details_json'      => ['present', 'array'],
            'event_start_at'    => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d H:i:s'],
            'event_ended_at'    => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d H:i:s', 'after_or_equal:event_start_at'],
        ];
    }

    public static function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $data     = $validator->getData();
            $startSet = ! empty($data['event_start_at']);
            $endSet   = ! empty($data['event_ended_at']);

            if ($startSet && ! $endSet) {
                $validator->errors()->add('event_ended_at', __('messages.exceptions.event_ended_at_required'));
            } elseif (! $startSet && $endSet) {
                $validator->errors()->add('event_start_at', __('messages.exceptions.event_start_at_required'));
            }
        });
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'force_create' => [
                'description' => 'Force creation even if a published product for same source (`course`, `seminar`, `digital_asset`) exists.
                 it will set the status of previous product to **archived**',
                'required' => true,
                'example'  => false,
            ],
            'productable_type' => [
                'description' => 'Type of the productable entity',
                'required'    => true,
                'example'     => 'course',
            ],
            'productable_id' => [
                'description' => 'ID of the productable entity',
                'required'    => true,
                'example'     => 1,
            ],
            'vendor_id' => [
                'description' => 'ID of the vendor',
                'required'    => true,
                'example'     => 1,
            ],
            'term_id' => [
                'description' => 'ID of the term',
                'required'    => true,
                'example'     => 1,
            ],
            'status' => [
                'description' => 'Publication status of the product',
                'required'    => true,
                'example'     => 'published',
            ],
            'is_visible' => [
                'description' => 'Whether the product is visible',
                'required'    => true,
                'example'     => true,
            ],
            'short_description' => [
                'description' => 'Short description of the product',
                'required'    => false,
                'example'     => 'A short summary of the product',
            ],
            'short_name' => [
                'description' => 'Short name of the product',
                'required'    => false,
                'example'     => 'PRD-001',
            ],
            'name' => [
                'description' => 'Name of the product',
                'required'    => true,
                'example'     => 'Mathematics Course',
            ],
            'is_featured' => [
                'description' => 'Whether the product is featured',
                'required'    => true,
                'example'     => false,
            ],
            'categories' => [
                'description' => 'Array of category IDs',
                'required'    => true,
                'example'     => [1, 2],
            ],
            'details_json' => [
                'description' => 'Additional details for the product (structure may vary by productable_type)',
                'required'    => true,
                'example'     => ['key' => 'value'],
            ],
            'event_start_at' => [
                'description' => 'Event start date in Y-m-d H:i:s format. Must be provided together with event_ended_at.',
                'required'    => false,
                'example'     => '1404-06-01 00:00:00',
            ],
            'event_ended_at' => [
                'description' => 'Event end date in Y-m-d H:i:s format. Must be on or after event_start_at.',
                'required'    => false,
                'example'     => '1404-06-30 23:59:59',
            ],
        ];
    }
}
