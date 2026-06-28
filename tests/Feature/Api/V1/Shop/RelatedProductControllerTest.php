<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\RelationTypeEnum;
use App\Models\Product;

describe('RelatedProductController', function () {
    beforeEach(function () {
        $this->product = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->create([
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);
        $this->upsellProduct = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->create([
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);
        $this->relatedProduct = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->create([
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);
        $this->crossSellProduct = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->create([
                'status' => PublicationStatusEnum::PUBLISHED,
            ]);

        $this->product->relatedProducts()->attach($this->upsellProduct->id, ['relation_type' => RelationTypeEnum::UPSELL]);
        $this->product->relatedProducts()->attach($this->relatedProduct->id, ['relation_type' => RelationTypeEnum::RELATED]);
        $this->product->relatedProducts()->attach($this->crossSellProduct->id, ['relation_type' => RelationTypeEnum::CROSS_SELL]);

    });

    it('returns related products', function (RelationTypeEnum $relationType, string $expectedProductProperty) {
        $response = $this->getJson(route('api.v1.shop.products.related', [
            'product'       => $this->product->slug,
            'relation_type' => $relationType->value,
        ]));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData))->toBe(1)
            ->and($responseData[0]['slug'])->toBe($this->{$expectedProductProperty}->slug);
    })->with([
        'RELATED'    => [RelationTypeEnum::RELATED, 'relatedProduct'],
        'CROSS_SELL' => [RelationTypeEnum::CROSS_SELL, 'crossSellProduct'],
        'UPSELL'     => [RelationTypeEnum::UPSELL, 'upsellProduct'],
    ]);

    it('does not return unpublished related products', function () {
        $unpublishedProduct = Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->create([
                'status' => PublicationStatusEnum::DRAFT,
            ]);
        $this->product->relatedProducts()->attach($unpublishedProduct->id, ['relation_type' => RelationTypeEnum::RELATED]);

        $response = $this->getJson(route('api.v1.shop.products.related', [
            'product'       => $this->product->slug,
            'relation_type' => RelationTypeEnum::RELATED->value,
        ]));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData))->toBe(1)
            ->and($responseData[0]['slug'])->toBe($this->relatedProduct->slug);
    });

    it('returns empty array when no related products exist for the given relation type', function () {

        $response = $this->getJson(route('api.v1.shop.products.related', [
            'product' => Product::factory()->withCourse()->withDeliveryOptions(1)->create([
                'status' => PublicationStatusEnum::PUBLISHED,
            ])->slug,
            'relation_type' => RelationTypeEnum::CROSS_SELL->value,
        ]));

        $response->assertStatus(200);
        $responseData = $response->json('data');

        expect(count($responseData))->toBe(0);
    });
});
