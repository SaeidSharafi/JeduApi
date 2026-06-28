<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer();
});

it('returns only direct_download enrollments', function (): void {
    // Create one DIRECT_DOWNLOAD enrollment and one LMS_MOODLE enrollment
    createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    $data = $response->json('data.data');
    expect($data)->toHaveCount(1);
});

it('does not return other users digital asset enrollments', function (): void {
    $other = App\Models\User::factory()->create();
    createEnrollment($other, DeliveryMethodEnum::DIRECT_DOWNLOAD);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(0);
});

it('returns empty list when user has no direct_download enrollments', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(0);
});

it('paginates digital asset enrollments', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, count: 3);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index', ['per_page' => 1]));

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(1)
        ->and($response->json('data.total'))->toBe(3);
});
