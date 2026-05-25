<?php

declare(strict_types=1);

use App\Models\ContactUsRequest;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

describe('ContactPageController', function () {
    it('shows contact page settings', function () {
        $response = getJson(route('api.v1.shop.contactpage.show'));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'title',
                'subtitle',
                'main_links',
                'social_links',
                'address',
                'phone',
                'email',
                'map_embed_url',
            ],
        ]);
    });
});
describe('ContactUsRequestController', function () {
    it('stores contact us request', function () {
        // Arrange
        $payload = [
            'full_name' => 'John Doe',
            'phone'     => '+1234567890',
            'subject'   => 'Inquiry',
            'email'     => 'john@example.com',
            'message'   => 'Hello, I am interested in your courses.',
        ];

        // Act
        $response = postJson(route('api.v1.shop.contactus.store'), $payload);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('message', __('shop.responses.contact_form_submitted'));
        expect(ContactUsRequest::where('email', 'john@example.com')->exists())->toBeTrue();
    });

    it('throttles contact us requests', function () {
        $payload = [
            'full_name' => 'Jane Doe',
            'phone'     => '+1234567891',
            'subject'   => 'Spam',
            'email'     => 'jane@example.com',
            'message'   => 'Spam message.',
        ];

        for ($i = 0; $i < 10; $i++) {
            postJson(route('api.v1.shop.contactus.store'), $payload);
        }
        $response = postJson(route('api.v1.shop.contactus.store'), $payload);
        $response->assertTooManyRequests();
    });

});
