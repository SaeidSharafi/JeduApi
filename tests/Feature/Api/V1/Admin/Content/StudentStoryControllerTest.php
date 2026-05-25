<?php

declare(strict_types=1);
use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\StudentStory;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('StudentStoryController', function (): void {
    beforeEach(function (): void {
        Illuminate\Http\UploadedFile::fake();
        Storage::fake('public');
        $this->avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
            ->toDisk('public')
            ->upload();
    });
    it('list and fitler stories', function (): void {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_VIEW_ANY]);
        $category      = App\Models\Category::factory()->create();
        $otherCategory = App\Models\Category::factory()->create();
        $course        = App\Models\Course::factory()->create();
        $otherCourse   = App\Models\Course::factory()->create();
        $storyOne      = StudentStory::factory()->create([
            'student_name' => 'John Doe',
            'course_name'  => 'Laravel Basics',
            'is_visible'   => true,
        ]);
        $storyTwo = StudentStory::factory()->create([
            'student_name' => 'Jane Smith',
            'course_name'  => 'Vue.js Essentials',
            'is_visible'   => false,
        ]);
        $storyOne->categories()->attach($category->id);
        $storyOne->courses()->attach($course->id);
        $storyTwo->categories()->attach($otherCategory->id);
        $storyTwo->courses()->attach($otherCourse->id);

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

        // Test filtering by course ID
        $response = $this->getJson("/api/v1/admin/settings/student-stories?filter[course_id]={$course->id}");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['student_name' => 'John Doe']);

        // Test filtering by category ID
        $response = $this->getJson("/api/v1/admin/settings/student-stories?filter[category_id]={$category->id}");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['student_name' => 'John Doe']);
    });

    it('shows a specific story', function (): void {
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

    it('creates a new story', function (): void {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_CREATE]);
        $category      = App\Models\Category::factory()->create();
        $otherCategory = App\Models\Category::factory()->create();
        $course        = App\Models\Course::factory()->create();

        $postData = [
            'student_name' => 'Alice Johnson',
            'course_name'  => 'React for Beginners',
            'course_url'   => 'https://example.com/courses/react-for-beginners',
            'story_text'   => 'This course was amazing!',
            'is_visible'   => true,
            'avatar'       => $this->avatar->id,
            'categories'   => [$category->id, $otherCategory->id],
            'courses'      => [$course->id],
        ];

        $response = $this->postJson('/api/v1/admin/settings/student-stories', $postData);
        $response->assertStatus(201);
        $responseData = $response->json('data');
        $this->assertDatabaseHas('student_stories', [
            'student_name' => 'Alice Johnson',
            'course_name'  => 'React for Beginners',
            'story_text'   => 'This course was amazing!',
            'is_visible'   => true,
        ]);
        $this->assertDatabaseHas('course_student_story', [
            'course_id'        => $course->id,
            'student_story_id' => $responseData['id'],
        ]);
        $this->assertDatabaseHas('categorizables', [
            'category_id'        => $category->id,
            'categorizable_id'   => $responseData['id'],
            'categorizable_type' => MorphTypeEnum::STUDENT_STORY->value,
        ]);

        $story = StudentStory::where('student_name', 'Alice Johnson')->first();
        expect($story)->not->toBeNull()
            ->and($story->avatar_url)->toBe($this->avatar->getUrl())
            ->and($story->categories->pluck('id')->toArray())->toEqualCanonicalizing([$category->id, $otherCategory->id])
            ->and($story->courses->pluck('id')->toArray())->toEqualCanonicalizing([$course->id]);
    });
    it('creates a new story without avatar', function (): void {
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

    it('updates an existing story', function (): void {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_UPDATE]);
        $oldCategory = App\Models\Category::factory()->create();
        $newCategory = App\Models\Category::factory()->create();
        $oldCourse   = App\Models\Course::factory()->create();
        $newCourse   = App\Models\Course::factory()->create();
        $story       = StudentStory::factory()->create([
            'student_name' => 'Bob Brown',
            'course_name'  => 'Django Fundamentals',
            'is_visible'   => false,
        ])->fresh();

        $story->attachMedia($this->avatar, 'avatar');
        $story->categories()->attach($oldCategory->id);
        $story->courses()->attach($oldCourse->id);

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
            'categories'   => [$newCategory->id],
            'courses'      => [$newCourse->id],
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

        $this->assertDatabaseHas('course_student_story', [
            'course_id'        => $newCourse->id,
            'student_story_id' => $story->id,
        ]);
        $this->assertDatabaseHas('categorizables', [
            'category_id'        => $newCategory->id,
            'categorizable_id'   => $story->id,
            'categorizable_type' => MorphTypeEnum::STUDENT_STORY->value,
        ]);

        $updatedStory = StudentStory::find($story->id);
        $storyAvatar  = $updatedStory->firstMedia('avatar');
        expect($updatedStory)->not->toBeNull()
            ->and($storyAvatar->id)->toEqual($avatar->id)
            ->and($storyAvatar->getUrl())->toBe($avatar->getUrl())
            ->and($updatedStory->categories->pluck('id')->toArray())->toEqualCanonicalizing([$newCategory->id])
            ->and($updatedStory->courses->pluck('id')->toArray())->toEqualCanonicalizing([$newCourse->id]);
    });

    it('updates an existing story and set avatar to null', function (): void {
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
            ->and($storyAvatar)->toBeNull()
            ->and($updatedStory->avatar_url)->toBeNull();
    });

    it('deletes a story', function (): void {
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

    it('validates input data when creating a story', function (): void {
        $this->authorized_user([PermissionEnum::STUDENT_STORY_CREATE]);

        // Missing required fields
        $postData = [
            'course_name' => 'React for Beginners',
            // 'student_name' is missing
            'course_url' => 'its-still-valid-url',
            'story_text' => '',
            'is_visible' => true,
            // 'avatar' is missing
        ];

        $response = $this->postJson('/api/v1/admin/settings/student-stories', $postData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_name', 'story_text']);
    });

    it('prevents unauthorized access', function (): void {
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
