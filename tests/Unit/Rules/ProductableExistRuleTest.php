<?php

declare(strict_types=1);

use App\Rules\ProductableExistRule;
use Illuminate\Support\Facades\Validator;

describe('ProductableExistRule', function (): void {
    it('ignore checks if productable_type is empty or invalid', function (): void {
        $rule      = new ProductableExistRule();
        $course    = App\Models\Course::factory()->create()->fresh();
        $validator = Validator::make(
            [
                'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
                'force_create'     => false,
                'productable_id'   => $course->id,
                'productable_type' => null,
            ],
            [
                'productable_id' => [$rule],
            ]
        );
        expect($validator->passes())->toBeTrue();

        $validator = Validator::make(
            [
                'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
                'force_create'     => false,
                'productable_id'   => $course->id,
                'productable_type' => 'invalid_type',
            ],
            [
                'productable_id' => [$rule],
            ]
        );
        expect($validator->passes())->toBeTrue();

    });
    it('pass the check if status is not PUBLISHED', function (): void {
        $rule    = new ProductableExistRule();
        $course  = App\Models\Course::factory()->create()->fresh();
        $product = App\Models\Product::factory()->create([
            'productable_id'   => $course->id,
            'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
        ]);
        $validator = Validator::make(
            [
                'status'           => App\Enums\PublicationStatusEnum::DRAFT->value,
                'force_create'     => true,
                'productable_id'   => $course->id,
                'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            ],
            [
                'productable_id' => [$rule],
            ]
        );
        expect($validator->passes())->toBeTrue();
    });
    it('pass the check if force_create is true', function (): void {
        $rule    = new ProductableExistRule();
        $course  = App\Models\Course::factory()->create()->fresh();
        $product = App\Models\Product::factory()->create([
            'productable_id'   => $course->id,
            'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
        ]);
        $validator = Validator::make(
            [
                'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
                'force_create'     => true,
                'productable_id'   => $course->id,
                'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            ],
            [
                'productable_id' => [$rule],
            ]
        );
        expect($validator->passes())->toBeTrue();
    });

    it('fail the check if force_create is false', function (): void {
        $rule    = new ProductableExistRule();
        $course  = App\Models\Course::factory()->create()->fresh();
        $product = App\Models\Product::factory()->create([
            'productable_id'   => $course->id,
            'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
        ]);
        $validator = Validator::make(
            [
                'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
                'force_create'     => false,
                'productable_id'   => $course->id,
                'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            ],
            [
                'productable_id' => [$rule],
            ]
        );
        expect($validator->fails())->toBeTrue();

    });
    it('fail the check if prodtable doesn\'t exist', function (): void {
        $rule    = new ProductableExistRule();
        $course  = App\Models\Course::factory()->create()->fresh();
        $product = App\Models\Product::factory()->create([
            'productable_id'   => $course->id,
            'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
        ]);
        $validator = Validator::make(
            [
                'status'           => App\Enums\PublicationStatusEnum::PUBLISHED->value,
                'force_create'     => false,
                'productable_id'   => 9999,
                'productable_type' => App\Enums\ProductableEnum::COURSE->value,
            ],
            [
                'productable_id' => [$rule],
            ]
        );
        expect($validator->fails())->toBeTrue();

    });
});
