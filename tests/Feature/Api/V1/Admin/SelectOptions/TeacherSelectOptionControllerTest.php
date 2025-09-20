<?php

declare(strict_types=1);

use App\Models\Teacher;

uses(Tests\AuthTestTrait::class);
describe('Admin Teacher Select Option API', function (): void {
    it('returns filtered teacher select options', function (): void {
        $this->authorized_user();
        Storage::fake('public');
        $profile = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('profile.jpg'))
            ->toDisk('public')
            ->upload();
        Teacher::factory()->count(3)
            ->afterCreating(function (Teacher $teacher) use ($profile): void {
                $teacher->attachMedia($profile->id, 'profile');
            })
            ->create();
        $teacher = Teacher::factory()
            ->afterCreating(function (Teacher $teacher) use ($profile): void {
                $teacher->attachMedia($profile->id, 'profile');
            })
            ->create([
                'first_name' => 'Test',
                'last_name'  => 'Teacher',
                'email'      => 'example@example.com',
                'phone'      => '09305214697',
            ])->fresh();
        $response = $this->getJson(
            route('api.v1.admin.select-option.teacher', ['q' => 'Test Teacher'])
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
            'title'     => 'Test Teacher',
            'subtitle'  => 'example@example.com (09305214697)',
            'image_url' => $profile->getUrl(),
        ]);
    });

    it('returns empty data if no match', function (): void {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.teacher', ['q' => 'NoSuchTeacher'])
        );
        $response->assertOk();
        $response->assertJson(['data' => []]);
    });
});
