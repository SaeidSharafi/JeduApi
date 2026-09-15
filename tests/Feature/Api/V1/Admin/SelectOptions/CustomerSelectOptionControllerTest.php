<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\UserSelectOptionData;
use App\Http\Controllers\Api\Admin\SelectOptions\CustomerSelectOptionController;
use App\Models\User;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(CustomerSelectOptionController::class);
covers(UserSelectOptionData::class);

describe('Admin Customer Select Option API', function (): void {
    beforeEach(function (): void {
        $this->authorized_user();
    });

    it('returns customer select options', function (): void {
        User::factory()->count(3)->create();
        User::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'TestDoe',
            'email'      => 'john.testdoe@example.com',
            'phone'      => '09301112233',
        ]);

        $response = $this->getJson(
            route('api.v1.admin.select-option.customers', ['q' => 'TestDoe'])
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'avatar_url',
                    ],
                ],
                'last_page',
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonFragment([
            'title'    => 'John TestDoe',
            'subtitle' => 'john.testdoe@example.com (09301112233)',
        ]);
        $response->assertJsonMissingPath('data.data.0.first_name');
        $response->assertJsonMissingPath('data.data.0.last_name');
        $response->assertJsonMissingPath('data.data.0.email');
        $response->assertJsonMissingPath('data.data.0.phone');
    });

    it('searches customers by civil id and phone', function (): void {
        User::factory()->create([
            'first_name' => 'Jane',
            'last_name'  => 'Searchable',
            'email'      => 'jane.searchable@example.com',
            'phone'      => '09120000000',
            'civil_id'   => '1234567890',
        ]);
        User::factory()->count(2)->create();

        $byCivilId = $this->getJson(
            route('api.v1.admin.select-option.customers', ['q' => '1234567890'])
        );
        $byCivilId->assertOk();
        $byCivilId->assertJsonCount(1, 'data.data');
        $byCivilId->assertJsonPath('data.data.0.title', 'Jane Searchable');

        $byPhone = $this->getJson(
            route('api.v1.admin.select-option.customers', ['q' => '09120000000'])
        );
        $byPhone->assertOk();
        $byPhone->assertJsonCount(1, 'data.data');
        $byPhone->assertJsonPath('data.data.0.title', 'Jane Searchable');
    });

    it('paginates the customers returned', function (): void {
        User::factory()->count(20)->create();

        $response = $this->getJson(route('api.v1.admin.select-option.customers'));

        $response->assertOk();
        $response->assertJsonCount(15, 'data.data');
        $response->assertJsonPath('data.per_page', 15);
        $response->assertJsonPath('data.total', 20);
        $response->assertJsonPath('data.last_page', 2);
    });

    it('returns empty data if no match', function (): void {
        $response = $this->getJson(
            route('api.v1.admin.select-option.customers', ['q' => 'NoSuchCustomer'])
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'data.data');
        $response->assertJsonPath('data.total', 0);
    });
});
