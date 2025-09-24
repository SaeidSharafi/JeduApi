<?php

use App\Enums\PartnerShowInEnum;
use App\Models\Partner;

describe('PartnerController', function () {

    it('can fetch partners', function () {
        Partner::factory(10)
            ->create(
                ['is_active' => true]
            );
        $response = $this->getJson(route('api.v1.shop.partners.index'));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'title',
                    'caption',
                    'image_url',
                    'url',
                    'order',
                ],
            ],
        ]);
        $this->assertCount(10, $response->json('data'));
    });

    it('can fetch partners filtered by show_in', function () {
        Partner::factory(5)
            ->create(
                [
                    'is_active' => true,
                    'show_in' => PartnerShowInEnum::HOME,
                ]
            );
        Partner::factory(5)
            ->create(
                [
                    'is_active' => true,
                    'show_in' => PartnerShowInEnum::COURSE,
                ]
            );
        $response = $this->getJson(route('api.v1.shop.partners.index', ['show_in' => PartnerShowInEnum::HOME->value]));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'title',
                    'caption',
                    'image_url',
                    'url',
                    'order',
                ],
            ],
        ]);
        $this->assertCount(5, $response->json('data'));

    });
});
