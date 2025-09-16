<?php
declare(strict_types=1);
uses(Tests\AuthTestTrait::class);
describe('Admin Staff Select Option API', function () {

    it('returns filtered staff select options', function () {
        $this->authorized_user();
        App\Models\Staff::factory()->count(3)->create();
        App\Models\Staff::factory()->create([
            'name'  => 'John XDoe',
            'email' => 'example@example.com',
            'phone' => '1234567890',
        ]);
        $response = $this->getJson('/api/v1/admin/select-option/staff?q=John XDoe');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'subtitle',
                    'image_url',
                ],
            ],
        ]);
        $response->assertJsonFragment([
            'title'  => 'John XDoe',
            'subtitle' => 'example@example.com',
        ]);
    });

    it('returns empty data if no match', function () {
        $this->authorized_user();
        $response = $this->getJson(
            '/api/v1/admin/select-option/staff?q=NoSuchStaff'
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });

    it('filters by email and phone', function () {
        $this->authorized_user();
        App\Models\Staff::factory()->create([
            'name'  => 'Jane XSmith',
            'email' => 'example@example.com',
            'phone' => '1234567890',
        ]);
        $response = $this->getJson('/api/v1/admin/select-option/staff?q=example@example.com');
        $response->assertOk();
        $response->assertJsonFragment([
            'title'  => 'Jane XSmith',
            'subtitle' => 'example@example.com',
        ]);
        $response = $this->getJson('/api/v1/admin/select-option/staff?q=1234567890');
        $response->assertOk();
        $response->assertJsonFragment([
            'title'  => 'Jane XSmith',
            'subtitle' => 'example@example.com',
        ]);
    });

    it('limits the number of results', function () {
        $this->authorized_user();
        App\Models\Staff::factory()->count(5)->create();
        $response = $this->getJson('/api/v1/admin/select-option/staff?limit=3');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    });
});
