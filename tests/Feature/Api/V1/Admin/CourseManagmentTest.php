<?php

it('can view list of courses', function (): void {
    $courses = \App\Models\Course::factory(5)->create();
    $response = $this->getJson(route('api.v1.admin.course.index'));
    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'slug',
                        'name',
                        'short_name',
                        'description',
                        'default_teahcer_info',
                        'meta_title',
                        'meta_description',
                        'meta_keywords',
                        'status',
                    ],
                ],
            ],
        ]);
});

it('can create a new course with valida data', function (): void {
    $courseData = \App\Models\Course::factory()->make()->toArray();
    $course = \App\Data\Course\CourseData::from($courseData);
    $response = $this->postJson(route('api.v1.admin.course.store'), $courseData);
    $response
        ->assertStatus(201)
        ->assertJson([
            'data' => [
                'slug'                 => $course->slug,
                'name'                 => $course->name,
                'short_name'           => $course->short_name,
                'description'          => $course->description,
                'default_teahcer_info' => $course->default_teahcer_info,
                'meta_title'           => $course->meta_title,
                'meta_description'     => $course->meta_description,
                'meta_keywords'        => $course->meta_keywords,
                'status'               => $course->status->translate(),
            ],
        ]);
});

it('can not create a new course with invalid data', function (): void {
    $courseData = \App\Models\Course::factory()->make([
        'slug'                 => null,
        'name'                 => null,
        'short_name'           => null,
        'description'          => null,
        'default_teahcer_info' => null,
        'meta_title'           => null,
        'meta_description'     => null,
        'meta_keywords'        => null,
        'status'               => null,
    ])->toArray();
    $response = $this->postJson(route('api.v1.admin.course.store'), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
            'name',
            'short_name',
            'status',
        ]);
});

it('can not create a new course with invalid slug', function (): void {
    $courseData = \App\Models\Course::factory()->make([
        'slug' => 'invalid slug',
    ])->toArray();
    $response = $this->postJson(route('api.v1.admin.course.store'), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

it('can view a course', function (): void {
    $course = \App\Models\Course::factory()->create();
    $response = $this->getJson(route('api.v1.admin.course.show', $course->id));
    $response
        ->assertStatus(200)
        ->assertJson([
            'data' => [
                'slug'                 => $course->slug,
                'name'                 => $course->name,
                'short_name'           => $course->short_name,
                'description'          => $course->description,
                'default_teahcer_info' => $course->default_teahcer_info,
                'meta_title'           => $course->meta_title,
                'meta_description'     => $course->meta_description,
                'meta_keywords'        => $course->meta_keywords,
                'status'               => $course->status->translate(),
            ],
        ]);
});

it('can edit a course', function (): void {
    $course = \App\Models\Course::factory()->create();
    $courseData = \App\Models\Course::factory()->make()->toArray();
    $response = $this->getJson(route('api.v1.admin.course.edit', $course->id));
    $response->assertSuccessful()
        ->assertJson([
            'data' => [
                'slug'                 => $course->slug,
                'name'                 => $course->name,
                'short_name'           => $course->short_name,
                'description'          => $course->description,
                'default_teahcer_info' => $course->default_teahcer_info,
                'meta_title'           => $course->meta_title,
                'meta_description'     => $course->meta_description,
                'meta_keywords'        => $course->meta_keywords,
                'status'               => $course->status->value,
            ],
        ]);
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), $courseData);
    $response
        ->assertStatus(200)
        ->assertJson([
            'data' => [
                'slug'                 => $courseData['slug'],
                'name'                 => $courseData['name'],
                'short_name'           => $courseData['short_name'],
                'description'          => $courseData['description'],
                'default_teahcer_info' => $courseData['default_teahcer_info'],
                'meta_title'           => $courseData['meta_title'],
                'meta_description'     => $courseData['meta_description'],
                'meta_keywords'        => $courseData['meta_keywords'],
                'status'               => \App\Enums\CourseStatusEnum::tryFrom($courseData['status'])->translate(),
            ],
        ]);
});

it('can not edit a course with invalid data', function (): void {
    $course = \App\Models\Course::factory()->create();
    $courseData = \App\Models\Course::factory()->make([
        'slug'                 => null,
        'name'                 => null,
        'short_name'           => null,
        'description'          => null,
        'default_teahcer_info' => null,
        'meta_title'           => null,
        'meta_description'     => null,
        'meta_keywords'        => null,
        'status'               => null,
    ])->toArray();
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
            'name',
            'short_name',
            'status',
        ]);
});

it('can not edit a course with invalid slug', function (): void {
    $course = \App\Models\Course::factory()->create();
    $courseData = \App\Models\Course::factory()->make([
        'slug' => 'invalid slug',
    ])->toArray();
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

it('can delete a course', function (): void {
    $course = \App\Models\Course::factory()->create();
    $response = $this->deleteJson(route('api.v1.admin.course.destroy', $course->id));
    $response->assertStatus(204);
    $this->assertDatabaseMissing('courses', [
        'id' => $course->id,
    ]);
});
