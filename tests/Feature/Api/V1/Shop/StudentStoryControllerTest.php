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

    it('return student story data correctly', function (): void {
        $story = StudentStory::factory()->create(
            ['is_visible' => true]
        );
        Storage::fake('public');
        $avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
            ->toDisk('public')
            ->upload();
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
