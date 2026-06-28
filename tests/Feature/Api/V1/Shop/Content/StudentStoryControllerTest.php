<?php

declare(strict_types=1);

use App\Models\StudentStory;

describe('StudentStoryController', function (): void {

    it('can fetch student stories', function (): void {
        StudentStory::factory(5)->create(
            ['is_visible' => true]
        );
        StudentStory::factory(3)->create(
            ['is_visible' => false]
        );
        $response = $this->getJson(route('api.v1.shop.student-stories.index'));
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'student_name',
                    'avatar_url',
                    'course_name',
                    'course_url',
                    'story_text',
                    'display_order',
                ],
            ],
            'metadata',
        ]);
        $this->assertCount(5, $response->json('data'));
    });
    it('can fetch featured student stories', function (): void {
        $featuredStories = StudentStory::factory(4)->create(
            ['is_visible' => true, 'is_featured' => true]
        );
        StudentStory::factory(2)->create(
            ['is_visible' => true, 'is_featured' => false]
        );
        $response = $this->getJson(route('api.v1.shop.student-stories.index', [
            'featured_only' => true,
        ]));
        $response->assertOk();
        $responseData = collect($response->json('data'));

        $this->assertCount(4, $responseData);
        foreach ($featuredStories as $story) {
            expect($story->student_name)->toBeInCollection($responseData, 'student_name')
                ->and($story->course_name)->toBeInCollection($responseData, 'course_name')
                ->and($story->course_url)->toBeInCollection($responseData, 'course_url')
                ->and($story->story_text)->toBeInCollection($responseData, 'story_text')
                ->and($story->display_order)->toBeInCollection($responseData, 'display_order');
        }
    });
    it('can fetch student stories by course_slug', function (): void {
        $storyInCourse = StudentStory::factory()->create(
            ['is_visible' => true]
        );
        $storyOutOfCourse = StudentStory::factory()->create(
            ['is_visible' => true]
        );
        $courseSlug = 'example-course-slug';

        $storyInCourse->courses()->attach(
            App\Models\Course::factory()->create(['slug' => $courseSlug])->id
        );

        $response = $this->getJson(route('api.v1.shop.student-stories.index', [
            'course_slug' => $courseSlug,
        ]));
        $response->assertOk();
        $responseData = $response->json('data');

        $this->assertCount(1, $responseData);
        expect($storyInCourse->student_name)->toEqual($responseData[0]['student_name'])
            ->and($storyInCourse->course_name)->toEqual($responseData[0]['course_name'])
            ->and($storyInCourse->course_url)->toEqual($responseData[0]['course_url'])
            ->and($storyInCourse->story_text)->toEqual($responseData[0]['story_text'])
            ->and($storyInCourse->display_order)->toEqual($responseData[0]['display_order']);
    });
    it('can fetch student stories by category_slug', function (): void {
        $storyInCategory = StudentStory::factory()->create(
            ['is_visible' => true]
        );
        $storyOutOfCategory = StudentStory::factory()->create(
            ['is_visible' => true]
        );
        $categorySlug = 'example-category-slug';

        $storyInCategory->categories()->attach(
            App\Models\Category::factory()->create(['slug' => $categorySlug])->id
        );

        $response = $this->getJson(route('api.v1.shop.student-stories.index', [
            'category_slug' => $categorySlug,
        ]));
        $response->assertOk();
        $responseData = $response->json('data');

        $this->assertCount(1, $responseData);
        expect($storyInCategory->student_name)->toEqual($responseData[0]['student_name'])
            ->and($storyInCategory->course_name)->toEqual($responseData[0]['course_name'])
            ->and($storyInCategory->course_url)->toEqual($responseData[0]['course_url'])
            ->and($storyInCategory->story_text)->toEqual($responseData[0]['story_text'])
            ->and($storyInCategory->display_order)->toEqual($responseData[0]['display_order']);
    });
    it('will fetch featured stories if no stories found for given course_slug', function (): void {
        $featuredStory = StudentStory::factory()->create(
            ['is_visible' => true, 'is_featured' => true]
        );
        $nonFeaturedStory = StudentStory::factory()->create(
            ['is_visible' => true, 'is_featured' => false]
        );

        $response = $this->getJson(route('api.v1.shop.student-stories.index', [
            'course_slug' => 'non-existent-course-slug',
        ]));
        $response->assertOk();
        $responseData = $response->json('data');

        $this->assertCount(1, $responseData);
        expect($featuredStory->student_name)->toEqual($responseData[0]['student_name'])
            ->and($featuredStory->course_name)->toEqual($responseData[0]['course_name'])
            ->and($featuredStory->course_url)->toEqual($responseData[0]['course_url'])
            ->and($featuredStory->story_text)->toEqual($responseData[0]['story_text'])
            ->and($featuredStory->display_order)->toEqual($responseData[0]['display_order']);
    });

    it('will fetch featured stories if no stories found for given category_slug', function (): void {
        $featuredStory = StudentStory::factory()->create(
            ['is_visible' => true, 'is_featured' => true]
        );
        $nonFeaturedStory = StudentStory::factory()->create(
            ['is_visible' => true, 'is_featured' => false]
        );

        $response = $this->getJson(route('api.v1.shop.student-stories.index', [
            'category_slug' => 'non-existent-category-slug',
        ]));
        $response->assertOk();
        $responseData = $response->json('data');

        $this->assertCount(1, $responseData);
        expect($featuredStory->student_name)->toEqual($responseData[0]['student_name'])
            ->and($featuredStory->course_name)->toEqual($responseData[0]['course_name'])
            ->and($featuredStory->course_url)->toEqual($responseData[0]['course_url'])
            ->and($featuredStory->story_text)->toEqual($responseData[0]['story_text'])
            ->and($featuredStory->display_order)->toEqual($responseData[0]['display_order']);
    });

    it('return student story data correctly', function (): void {
        Storage::fake('public');
        $avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
            ->toDisk('public')
            ->upload();
        $story = StudentStory::factory()->create(
            [
                'is_visible' => true,
                'avatar_url' => $avatar->getUrl(),
            ]
        );
        $story->attachMedia($avatar, 'avatar');
        $response = $this->getJson(route('api.v1.shop.student-stories.index'));
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'student_name',
                    'avatar_url',
                    'course_name',
                    'course_url',
                    'story_text',
                    'display_order',
                ],
            ],
            'metadata',
        ]);

        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals($story->student_name, $responseData[0]['student_name']);
        $this->assertEquals($story->course_name, $responseData[0]['course_name']);
        $this->assertEquals($story->course_url, $responseData[0]['course_url']);
        $this->assertEquals($story->story_text, $responseData[0]['story_text']);
        $this->assertEquals($story->display_order, $responseData[0]['display_order']);
        $this->assertEquals($story->firstMedia('avatar')->getUrl(), $responseData[0]['avatar_url']);

    });

    it('stories are ordered by display_order', function (): void {
        StudentStory::factory()->create([
            'student_name'  => 'Student One',
            'display_order' => 2,
            'is_visible'    => true,
        ]);
        StudentStory::factory()->create([
            'student_name'  => 'Student Two',
            'display_order' => 1,
            'is_visible'    => true,
        ]);
        StudentStory::factory()->create([
            'student_name'  => 'Student Three',
            'display_order' => 3,
            'is_visible'    => true,
        ]);
        $response = $this->getJson(route('api.v1.shop.student-stories.index'));
        $response->assertOk();
        $responseData = $response->json('data');
        $this->assertCount(3, $responseData);
        $this->assertEquals('Student Two', $responseData[0]['student_name']);
        $this->assertEquals('Student One', $responseData[1]['student_name']);
        $this->assertEquals('Student Three', $responseData[2]['student_name']);
    });
});
