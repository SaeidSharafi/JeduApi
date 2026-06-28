<?php

declare(strict_types=1);

use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $this->avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
        ->toDisk('public')
        ->upload();
});

describe('list filters', function (): void {
    it('should filter by first name', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW_ANY]);
        App\Models\Teacher::factory(20)->create();
        App\Models\Teacher::factory()->create(['first_name' => 'JohnFirstName']);
        $response = $this->getJson(route('api.v1.admin.teachers.index', ['filter' => ['first_name' => 'JohnFirstName']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['first_name' => 'JohnFirstName']);
    });

    it('should filter by last name', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW_ANY]);
        App\Models\Teacher::factory(20)->create();
        App\Models\Teacher::factory()->create(['last_name' => 'DoeLastName']);
        $response = $this->getJson(route('api.v1.admin.teachers.index', ['filter' => ['last_name' => 'DoeLastName']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['last_name' => 'DoeLastName']);
    });
    it('should filter by email', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW_ANY]);
        App\Models\Teacher::factory(20)->create();
        App\Models\Teacher::factory()->create(['email' => 'teacher@example.com']);
        $response = $this->getJson(route('api.v1.admin.teachers.index', ['filter' => ['email' => 'teacher@example.com']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['email' => 'teacher@example.com']);
    });
    it('should filter by phone', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW_ANY]);
        App\Models\Teacher::factory(20)->create();
        App\Models\Teacher::factory()->create(['phone' => '1234567890']);
        $response = $this->getJson(route('api.v1.admin.teachers.index', ['filter' => ['phone' => '1234567890']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['phone' => '1234567890']);
    });
    it('should filter by first name and last name', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW_ANY]);
        App\Models\Teacher::factory(20)->create();
        App\Models\Teacher::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $response = $this->getJson(route('api.v1.admin.teachers.index', ['filter' => ['first_name' => 'John', 'last_name' => 'Doe']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['first_name' => 'John', 'last_name' => 'Doe']);
    });
});

describe('TeacherController Test', function (): void {
    it('should list teachers', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW_ANY]);
        App\Models\Teacher::factory(20)->create();
        $response = $this->getJson(route('api.v1.admin.teachers.index'));
        $response->assertOk();
        $response->assertJsonCount(15, 'data.data');
    });

    it('should create a teacher', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_CREATE]);
        $data          = App\Models\Teacher::factory()->make();
        $data['media'] = [
            'avatar' => $this->avatar->id,
        ];
        $data['birth_date'] = verta($data->birth_date)->format('Y-m-d');
        $response           = $this->postJson(route('api.v1.admin.teachers.store'), $data->toArray());
        $response->assertCreated();
        $this->assertDatabaseHas('teachers', [
            'email'      => $data->email,
            'avatar_url' => $this->avatar->getUrl(),
        ]);
    });

    it('should show a teacher', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_VIEW]);
        $teacher = App\Models\Teacher::factory()->create();
        $teacher->attachMedia($this->avatar->id, 'avatar');
        $response = $this->getJson(route('api.v1.admin.teachers.show', ['teacher' => $teacher]));
        $response->assertOk();
        $response->assertJsonFragment(['email' => $teacher->email]);
        $response
            ->assertJson(function (AssertableJson $json): void {
                $json->has('data.media.avatar')
                    ->where('data.media.avatar.0.id', $this->avatar->id)
                    ->etc();
            });
    });

    it('should update a teacher', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_UPDATE]);
        $teacher       = App\Models\Teacher::factory()->create();
        $data          = App\Models\Teacher::factory()->make();
        $data['media'] = [
            'avatar' => $this->avatar->id,
        ];
        $data['birth_date'] = verta($data->birth_date)->format('Y-m-d');
        $response           = $this->putJson(route('api.v1.admin.teachers.update', ['teacher' => $teacher]), $data->toArray());
        $response->assertOk();
        $this->assertDatabaseHas('teachers', [
            'id'         => $teacher->id,
            'email'      => $data->email,
            'avatar_url' => $this->avatar->getUrl(),
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $teacher->id,
            'mediable_type' => App\Enums\System\MorphTypeEnum::TEACHER->value,
            'media_id'      => $this->avatar->id,
        ]);
    });
    it('should remove teacher avatar if it\'s null', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_UPDATE]);
        $teacher = App\Models\Teacher::factory()->create();
        $teacher->attachMedia($this->avatar->id, 'avatar');
        $data          = App\Models\Teacher::factory()->make();
        $data['media'] = [
            'avatar' => null,
        ];
        $data['birth_date'] = verta($data->birth_date)->format('Y-m-d');
        $response           = $this->putJson(route('api.v1.admin.teachers.update', ['teacher' => $teacher]), $data->toArray());
        $response->assertOk();
        $this->assertDatabaseHas('teachers', [
            'id'         => $teacher->id,
            'email'      => $data->email,
            'avatar_url' => null,
        ]);
        $this->assertDatabaseMissing('mediables', [
            'mediable_id'   => $teacher->id,
            'mediable_type' => App\Enums\System\MorphTypeEnum::TEACHER->value,
            'media_id'      => $this->avatar->id,
        ]);
    });
    it('should delete a teacher', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_DELETE]);
        $teacher = App\Models\Teacher::factory()->create();
        $teacher->attachMedia($this->avatar->id, 'avatar');
        $response = $this->deleteJson(route('api.v1.admin.teachers.destroy', ['teacher' => $teacher]));
        $response->assertNoContent();
        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('mediables', [
            'mediable_id'   => $teacher->id,
            'mediable_type' => App\Enums\System\MorphTypeEnum::TEACHER->value,
            'media_id'      => $this->avatar->id,
        ]);
        $this->assertDatabaseMissing('media', ['id' => $this->avatar->id]);

    });
    it('should not delete a teacher with related data', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::TEACHER_DELETE]);
        $teacher  = App\Models\Teacher::factory()->create();
        $delivery = App\Models\ProductDeliveryOption::factory()->create();
        $delivery->teachers()->attach($teacher->id);
        $response = $this->deleteJson(route('api.v1.admin.teachers.destroy', ['teacher' => $teacher]));
        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('messages.errors.model_has_relationship_data',
                    ['related_model' => getModelLabel(App\Models\Product::class)]),
            ]);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    });
});
