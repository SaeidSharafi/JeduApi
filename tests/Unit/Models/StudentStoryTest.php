<?php

declare(strict_types=1);

use App\Models\StudentStory;

it('to array', function (): void {
    $studentStory = StudentStory::query()->create([
        'student_name'  => 'John Doe',
        'course_name'   => 'Laravel Basics',
        'course_url'    => 'https://example.com/laravel-basics',
        'story_text'    => 'This course was amazing!',
        'is_visible'    => true,
        'display_order' => 1,
    ]);

    expect($studentStory->toArray())
        ->toBeArray()
        ->toMatchArray([
            'id'            => $studentStory->id,
            'student_name'  => 'John Doe',
            'course_name'   => 'Laravel Basics',
            'course_url'    => 'https://example.com/laravel-basics',
            'story_text'    => 'This course was amazing!',
            'is_visible'    => true,
            'display_order' => 1,
            'created_at'    => $studentStory->created_at?->utc()->toJSON(),
            'updated_at'    => $studentStory->updated_at?->utc()->toJSON(),
        ]);

});

it('get avatar url correctly', function (): void {
    $studentStory = StudentStory::query()->create([
        'student_name'  => 'John Doe',
        'course_name'   => 'Laravel Basics',
        'course_url'    => 'https://example.com/laravel-basics',
        'story_text'    => 'This course was amazing!',
        'is_visible'    => true,
        'display_order' => 1,
    ]);

    expect($studentStory->avatar_url)->toBeNull();
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $avatar = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'))
        ->toDisk('public')
        ->upload();
    $studentStory->attachMedia($avatar, 'avatar');

    expect($studentStory->avatar_url)->toBeString();
});
