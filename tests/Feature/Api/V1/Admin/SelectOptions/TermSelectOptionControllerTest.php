<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('Admin Term Select Option API', function (): void {
    it('returns filtered term select options', function (): void {
        $this->authorized_user();
        App\Models\Term::factory()->count(3)->create();
        App\Models\Term::factory()->create([
            'name'          => 'TestTerm',
            'academic_year' => 'test-academic-year',
        ]);
        $response = $this->getJson(
            route('api.v1.admin.select-option.terms', ['q' => 'TestTerm'])
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
            'title'     => 'TestTerm',
            'subtitle'  => 'test-academic-year',
            'image_url' => null,
        ]);
    });

    it('returns empty data if no match', function (): void {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.terms', ['q' => 'NoSuchTerm'])
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });
});
