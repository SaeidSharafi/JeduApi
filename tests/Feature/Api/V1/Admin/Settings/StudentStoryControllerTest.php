<?php

declare(strict_types=1);
use App\Enums\PermissionEnum;
use App\Models\StudentStory;

uses(Tests\AuthTestTrait::class);

describe('StudentStoryController', function () {
    beforeEach(function () {
        Illuminate\Http\UploadedFile::fake();
        Storage::fake('public');
        $this->avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
            ->toDisk('public')
            ->upload();
    });
    it('list and fitler stories', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_VIEW_ANY]);

        // Create some student stories
        StudentStory::factory()->create([
            'student_name' => 'John Doe',
            'course_name'  => 'Laravel Basics',
            'is_visible'   => true,
        ]);
        StudentStory::factory()->create([
            'student_name' => 'Jane Smith',
            'course_name'  => 'Vue.js Essentials',
            'is_visible'   => false,
        ]);

        // Test listing all stories
        $response = $this->getJson('/api/v1/admin/settings/student-stories');
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.data');

        // Test filtering by student name
        $response = $this->getJson('/api/v1/admin/settings/student-stories?filter[student_name]=John Doe');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['student_name' => 'John Doe']);

        // Test filtering by visibility
        $response = $this->getJson('/api/v1/admin/settings/student-stories?filter[is_visible]=1');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['student_name' => 'John Doe']);
    });

    it('shows a specific story', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_VIEW]);

        $story = StudentStory::factory()->create([
            'student_name' => 'John Doe',
            'course_name'  => 'Laravel Basics',
            'is_visible'   => true,
        ])->fresh();

        $story->attachMedia($this->avatar, 'avatar');

        $response = $this->getJson("/api/v1/admin/settings/student-stories/{$story->id}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['student_name' => 'John Doe']);

        $responseData = $response->json('data');
        expect($responseData['student_name'])->toBe('John Doe')
            ->and($responseData['course_name'])->toBe('Laravel Basics')
            ->and($responseData['is_visible'])->toBe(true)
            ->and($responseData['avatar']['url'])->toBe($this->avatar->getUrl());

    });

    it('creates a new story', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_CREATE]);

        $postData = [
            'student_name' => 'Alice Johnson',
            'course_name'  => 'React for Beginners',
            'course_url'   => 'https://example.com/courses/react-for-beginners',
            'story_text'   => 'This course was amazing!',
            'is_visible'   => true,
            'avatar'       => $this->avatar->id,
        ];

        $response = $this->postJson('/api/v1/admin/settings/student-stories', $postData);
        $response->assertStatus(201);

        $this->assertDatabaseHas('student_stories', [
            'student_name' => 'Alice Johnson',
            'course_name'  => 'React for Beginners',
            'story_text'   => 'This course was amazing!',
            'is_visible'   => true,
        ]);

        $story = StudentStory::where('student_name', 'Alice Johnson')->first();
        expect($story)->not->toBeNull()
            ->and($story->avatar_url)->toBe($this->avatar->getUrl());
    });
    it('creates a new story without avatar', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_CREATE]);

        $postData = [
            'student_name' => 'Alice Johnson',
            'course_name'  => 'React for Beginners',
            'course_url'   => 'https://example.com/courses/react-for-beginners',
            'story_text'   => 'This course was amazing!',
            'is_visible'   => true,
            'avatar'       => $this->avatar->id,
        ];

        $response = $this->postJson('/api/v1/admin/settings/student-stories', $postData);
        $response->assertStatus(201);

        $this->assertDatabaseHas('student_stories', [
            'student_name' => 'Alice Johnson',
            'course_name'  => 'React for Beginners',
            'story_text'   => 'This course was amazing!',
            'is_visible'   => true,
        ]);

        $story = StudentStory::where('student_name', 'Alice Johnson')->first();
        expect($story)->not->toBeNull();
        $storyAvatar = $story->firstMedia('avatar');
        expect($storyAvatar->id)->toEqual($this->avatar->id)
            ->and($storyAvatar->getUrl())->toBe($this->avatar->getUrl());
    });

    it('updates an existing story', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_UPDATE]);

        $story = StudentStory::factory()->create([
            'student_name' => 'Bob Brown',
            'course_name'  => 'Django Fundamentals',
            'is_visible'   => false,
        ])->fresh();

        $avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
            ->toDisk('public')
            ->upload();

        $updateData = [
            'student_name' => 'Robert Brown',
            'course_name'  => 'Django Advanced',
            'course_url'   => 'https://example.com/courses/django-advanced',
            'story_text'   => 'Learned a lot!',
            'is_visible'   => true,
            'avatar'       => $avatar->id,
        ];

        $response = $this->putJson("/api/v1/admin/settings/student-stories/{$story->id}", $updateData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('student_stories', [
            'id'           => $story->id,
            'student_name' => 'Robert Brown',
            'course_name'  => 'Django Advanced',
            'story_text'   => 'Learned a lot!',
            'is_visible'   => true,
        ]);

        $updatedStory = StudentStory::find($story->id);
        $storyAvatar  = $updatedStory->firstMedia('avatar');
        expect($updatedStory)->not->toBeNull()
            ->and($storyAvatar->id)->toEqual($avatar->id)
            ->and($storyAvatar->getUrl())->toBe($avatar->getUrl());
    });

    it('updates an existing story and set avatar to null', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_UPDATE]);

        $story = StudentStory::factory()->create([
            'student_name' => 'Bob Brown',
            'course_name'  => 'Django Fundamentals',
            'is_visible'   => false,
        ])->fresh();
        $story->attachMedia($this->avatar, 'avatar');

        $updateData = [
            'student_name' => 'Robert Brown',
            'course_name'  => 'Django Advanced',
            'course_url'   => 'https://example.com/courses/django-advanced',
            'story_text'   => 'Learned a lot!',
            'is_visible'   => true,
        ];

        $response = $this->putJson("/api/v1/admin/settings/student-stories/{$story->id}", $updateData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('student_stories', [
            'id'           => $story->id,
            'student_name' => 'Robert Brown',
            'course_name'  => 'Django Advanced',
            'story_text'   => 'Learned a lot!',
            'is_visible'   => true,
        ]);

        $updatedStory = StudentStory::find($story->id);
        $storyAvatar  = $updatedStory->firstMedia('avatar');
        expect($updatedStory)->not->toBeNull()
            ->and($storyAvatar)->toBeNull();
    });

    it('deletes a story', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_DELETE]);

        $story = StudentStory::factory()->create([
            'student_name' => 'Charlie Davis',
            'course_name'  => 'Python Basics',
            'is_visible'   => true,
        ])->fresh();
        $story->attachMedia($this->avatar, 'avatar');

        $response = $this->deleteJson("/api/v1/admin/settings/student-stories/{$story->id}");
        $response->assertStatus(204);

        $this->assertDatabaseMissing('student_stories', [
            'id' => $story->id,
        ]);

        $this->assertDatabaseMissing('media', [
            'id' => $this->avatar->id,
        ]);
    });

    it('validates input data when creating a story', function () {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_CREATE]);

        // Missing required fields
        $postData = [
            'course_name' => 'React for Beginners',
            // 'student_name' is missing
            'course_url' => 'not-a-valid-url',
            'story_text' => '',
            'is_visible' => true,
            // 'avatar' is missing
        ];

        $response = $this->postJson('/api/v1/admin/settings/student-stories', $postData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_name', 'course_url', 'story_text']);
    });

    it('prevents unauthorized access', function () {
        // No permissions
        $this->unauthorized_user();
        $updateData = [
            'student_name' => 'Robert Brown',
            'course_name'  => 'Django Advanced',
            'course_url'   => 'https://example.com/courses/django-advanced',
            'story_text'   => 'Learned a lot!',
            'is_visible'   => true,
            'avatar'       => $this->avatar->id,
        ];
        $response = $this->getJson('/api/v1/admin/settings/student-stories');
        $response->assertStatus(403);

        $story    = StudentStory::factory()->create()->fresh();
        $response = $this->getJson("/api/v1/admin/settings/student-stories/{$story->id}");
        $response->assertStatus(403);

        $response = $this->postJson('/api/v1/admin/settings/student-stories', $updateData);
        $response->assertStatus(403);

        $response = $this->putJson("/api/v1/admin/settings/student-stories/{$story->id}", $updateData);
        $response->assertStatus(403);
    });

});
