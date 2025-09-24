<?php

declare(strict_types=1);
describe('FooterController', function () {

    it('retrieves footer settings successfully', function () {
        // Simulate a GET request to the footer endpoint
        $response = $this->getJson(route('api.v1.shop.footer.index'));

        // Assert that the response status is 200 OK
        $response->assertStatus(200);

        // Assert that the response structure matches the expected format
        $response->assertJsonStructure([
            'data' => [
                'logo_url',
                'logo_alt',
                'caption',
                'support_link',
                'support_email_address',
                'addresses',
                'categories',
                'main_links',
                'social_media_links',
                'certifications',
            ],
            'message',
        ]);
    });
});
