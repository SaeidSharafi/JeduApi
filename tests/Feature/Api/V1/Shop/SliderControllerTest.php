<?php

use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Factories\Sequence;

describe('SliderController', function () {


    it('can fetch the list of sliders', function () {
        \App\Models\Slider::factory()
            ->count(5)
            ->state(new Sequence(
                [
                    'order'  => 2,
                    'status' => PublicationStatusEnum::PUBLISHED
                ],
                [
                    'order'  => 1,
                    'status' => PublicationStatusEnum::DRAFT
                ],
                [
                    'order'  => 4,
                    'status' => PublicationStatusEnum::PUBLISHED
                ],
                [
                    'order'  => 5,
                    'status' => PublicationStatusEnum::ARCHIVED
                ],
                [
                    'order'  => 3,
                    'status' => PublicationStatusEnum::PUBLISHED
                ],
            ))
            ->create();
        $sliders = \App\Models\Slider::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->orderBy('order')
            ->get();
        $response = $this->getJson(route('api.v1.shop.sliders.index'));

        $response->assertStatus(200)
            ->assertJsonCount($sliders->count(), 'data')
            ->assertJson([
                'data' => $sliders->map(function ($slider) {
                    return [
                        'title'      => $slider->title,
                        'caption'    => $slider->caption,
                        'image_url'  => $slider->image_url,
                        'image_alt'  => $slider->image_alt,
                        'link'       => $slider->link,
                        'order'      => $slider->order,
                    ];
                })->toArray(),
            ]);

    });

    it('returns an empty array when there are no sliders', function () {
        $response = $this->getJson(route('api.v1.shop.sliders.index'));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    });
});
