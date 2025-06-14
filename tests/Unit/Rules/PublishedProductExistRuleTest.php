<?php

declare(strict_types=1);

use App\Rules\EmailOrPhoneRule;
use App\Rules\ProductableExistRule;
use App\Rules\PublishedProductExistRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);
describe('PublishedProductExistRule', function (): void {
    it('pass the check if there is no published product beside the current one', function (): void {
        $course = \App\Models\Course::factory()->create()->fresh();
        $product = \App\Models\Product::factory()->create([
            'productable_id' => $course->id,
            'productable_type' => \App\Enums\ProductableEnum::COURSE->value,
            'status' => \App\Enums\PublicationStatusEnum::DRAFT->value
        ]);
        $rule = new PublishedProductExistRule($product);
        $validator = Validator::make(
            [
                'status' => \App\Enums\PublicationStatusEnum::PUBLISHED->value,
            ],
            [
                'status' => [$rule]
            ]
        );
        expect($validator->passes())->toBeTrue();
    });
    it('pass the check if status is not published', function (): void {
        $course = \App\Models\Course::factory()->create()->fresh();
        $product = \App\Models\Product::factory()->create([
            'productable_id' => $course->id,
            'productable_type' => \App\Enums\ProductableEnum::COURSE->value,
            'status' => \App\Enums\PublicationStatusEnum::PUBLISHED->value
        ]);
        $rule = new PublishedProductExistRule($product);
        $validator = Validator::make(
            [
                'status' => \App\Enums\PublicationStatusEnum::DRAFT->value,
            ],
            [
                'status' => [$rule]
            ]
        );
        expect($validator->passes())->toBeTrue();
    });
    it('fail the check if there is no product', function (): void {
        $rule = new PublishedProductExistRule(null);
        $course = \App\Models\Course::factory()->create()->fresh();
        $validator = Validator::make(
            [
                'status' => \App\Enums\PublicationStatusEnum::PUBLISHED->value,
            ],
            [
                'status' => [$rule]
            ]
        );
        expect($validator->fails())->toBeTrue();

    });

    it('fails the check if there is another product with published status', function (): void {
        $course = \App\Models\Course::factory()->create()->fresh();
        $product = \App\Models\Product::factory()->create([
            'productable_id' => $course->id,
            'productable_type' => \App\Enums\ProductableEnum::COURSE->value,
            'status' => \App\Enums\PublicationStatusEnum::PUBLISHED->value
        ]);
        $product2 = \App\Models\Product::factory()->create([
            'productable_id' => $course->id,
            'productable_type' => \App\Enums\ProductableEnum::COURSE->value,
            'status' => \App\Enums\PublicationStatusEnum::DRAFT->value
        ]);
        $rule = new PublishedProductExistRule($product2);
        $validator = Validator::make(
            [
                'status' => \App\Enums\PublicationStatusEnum::PUBLISHED->value,
            ],
            [
                'status' => [$rule]
            ]
        );
        expect($validator->fails())->toBeTrue();
    });
});
