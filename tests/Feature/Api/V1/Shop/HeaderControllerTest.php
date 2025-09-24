<?php

declare(strict_types=1);

use App\Data\Admin\Settings\HeaderData;
use App\Models\Setting;

describe('HeaderController', function () {
    it('retrieves header settings successfully', function () {
        $response = $this->getJson('/api/v1/shop/header');
        $header   = Setting::getValue('header', HeaderData::getDefaults());
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                'logo_url',
                'navigation_links',
                'contact_phone',
                'contact_email',
            ],
        ]);

        $responseData = $response->json('data');
        expect($responseData['logo_url'])->toBe($header['logo_url'] ?? null)
            ->and($responseData['navigation_links'])->toBe($header['navigation_links'] ?? [])
            ->and($responseData['contact_phone'])->toBe($header['contact_phone'] ?? '')
            ->and($responseData['contact_email'])->toBe($header['contact_email'] ?? '');
    });
});
