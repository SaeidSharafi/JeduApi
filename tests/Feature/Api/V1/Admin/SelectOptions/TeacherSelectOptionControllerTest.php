<?php

declare(strict_types=1);

use App\Data\Admin\SelectOptions\TeacherSelectOptionData;
use App\Http\Controllers\Api\Admin\SelectOptions\TeacherSelectOptionController;
use App\Models\Teacher;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(TeacherSelectOptionController::class);
covers(TeacherSelectOptionData::class);

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
            route('api.v1.admin.select-option.teacherss', ['q' => 'Test Teacher'])
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
                        'image_url',
                    ],
                ],
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonFragment([
            'title'     => 'Test Teacher',
            'subtitle'  => 'example@example.com (09305214697)',
            'image_url' => $profile->getUrl(),
        ]);
        $response->assertJsonMissingPath('data.data.0.first_name');
        $response->assertJsonMissingPath('data.data.0.last_name');
        $response->assertJsonMissingPath('data.data.0.email');
        $response->assertJsonMissingPath('data.data.0.phone');
        $response->assertJsonMissingPath('data.data.0.media');
    });

    it('returns empty data if no match', function (): void {
        $this->authorized_user();
        $response = $this->getJson(
            route('api.v1.admin.select-option.teacherss', ['q' => 'NoSuchTeacher'])
        );
        $response->assertOk();
        $response->assertJsonCount(0, 'data.data');
    });

    it('returns an empty image url when the teacher has no profile media', function (): void {
        $this->authorized_user();
        Teacher::factory()->create([
            'first_name' => 'No',
            'last_name'  => 'Media',
            'email'      => 'nomedia@example.com',
            'phone'      => '09300000000',
        ]);

        $response = $this->getJson(
            route('api.v1.admin.select-option.teacherss', ['q' => 'nomedia@example.com'])
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'title'     => 'No Media',
            'image_url' => '',
        ]);
    });
});
