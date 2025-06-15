<?php

uses(\Tests\AuthTestTrait::class);
describe('Admin Term Select Option API', function () {
    it('returns filtered term select options', function () {
        $this->authorized_user();
        \App\Models\Term::factory()->count(3)->create();
        \App\Models\Term::factory()->create([
            'name' => 'TestTerm',
            'academic_year' => 'test-academic-year',
        ]);
        $response = $this->getJson(
            route('api.v1.admin.select-option.term', ['q' => 'TestTerm'])
        );

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
            'title' => 'TestTerm',
            'subtitle' => 'test-academic-year',
            'image_url' => null,
        ]);
    });

    it('returns empty data if no match', function () {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.term', ['q' => 'NoSuchTerm'])
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });
});
