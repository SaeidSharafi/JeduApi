<?php

declare(strict_types=1);

use App\Enums\System\MorphTypeEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('GoodForStartController', function (): void {
    it('sets good for start for valid course items in category', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_UPDATE]);

        // Create a category
        $category = Category::factory()->create();

        // Create course items and non-course items
        $course1   = Course::factory()->create();
        $course2   = Course::factory()->create();
        $nonCourse = Product::factory()->create();

        // Attach items to category via categorizables pivot table
        $category->courses()->attach($course1);
        $category->products()->attach($nonCourse);
        $category->courses()->attach($course2);

        // Prepare payload with course item IDs
        $payload = [
            'course_ids'     => [$course1->id, $course2->id],
            'good_for_start' => true,
        ];

        // Act as admin and make POST request
        $response = $this->postJson("/api/v1/admin/category/{$category->id}/good-for-start", $payload);

        // Assert 200 OK response
        $response->assertStatus(200);

        // Assert the good_for_start flag is set to true for the course items
        foreach ($payload['course_ids'] as $itemId) {
            $this->assertDatabaseHas('categorizables', [
                'categorizable_id'   => $itemId,
                'categorizable_type' => MorphTypeEnum::COURSE->value,
                'good_for_start'     => true,
            ]);
        }

        // Assert the non-course item remains unchanged
        $this->assertDatabaseHas('categorizables', [
            'categorizable_id'   => $nonCourse->id,
            'categorizable_type' => MorphTypeEnum::PRODUCT->value,
            'good_for_start'     => false,
        ]);
    });

    it('returns validation error when non-course items are included', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_UPDATE]);

        // Create a category
        $category = Category::factory()->create();

        // Create course items and non-course items
        $course = Course::factory()->create([
            'id' => 8888, // Ensure unique ID
        ]);
        $nonCourse = Product::factory()->create([
            'id' => 9999, // Ensure unique ID
        ]);

        // Attach items to category via categorizables pivot table
        $category->courses()->attach($course);
        $category->products()->attach($nonCourse);

        // Prepare payload with both course and non-course item IDs
        $payload = [
            'course_ids'     => [$course->id, $nonCourse->id],
            'good_for_start' => true,
        ];

        // Act as admin and make POST request
        $response = $this->postJson("/api/v1/admin/category/{$category->id}/good-for-start", $payload);

        // Assert 422 Unprocessable Entity response
        $response->assertStatus(422);

        // Assert validation error message for course_ids
        $response->assertJsonValidationErrors(['course_ids.1']);

        // Assert no changes were made to the database
        foreach ($payload['course_ids'] as $itemId) {
            $this->assertDatabaseHas('categorizables', [
                'categorizable_id'   => $itemId,
                'categorizable_type' => $itemId === $course->id ? MorphTypeEnum::COURSE->value : MorphTypeEnum::PRODUCT->value,
                'good_for_start'     => false,
            ]);
        }

    });

    it('returns validation error for invalid payload', function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_UPDATE]);

        // Create a category
        $category = Category::factory()->create();

        // Prepare invalid payloads
        $invalidPayloads = [
            // Missing course_ids
            [
                'good_for_start' => true,
            ],
            // course_ids not an array
            [
                'course_ids'     => 'not-an-array',
                'good_for_start' => true,
            ],
            // course_ids empty array
            [
                'course_ids'     => [],
                'good_for_start' => true,
            ],
            // course_ids contains non-integer
            [
                'course_ids'     => [1, 'two', 3],
                'good_for_start' => true,
            ],
            // course_ids contains non-existing ID
            [
                'course_ids'     => [9999],
                'good_for_start' => true,
            ],
            // Missing good_for_start
            [
                'course_ids' => [1, 2, 3],
            ],
            // good_for_start not boolean
            [
                'course_ids'     => [1, 2, 3],
                'good_for_start' => 'not-boolean',
            ],
        ];

        foreach ($invalidPayloads as $payload) {
            // Act as admin and make POST request
            $response = $this->postJson("/api/v1/admin/category/{$category->id}/good-for-start", $payload);

            // Assert 422 Unprocessable Entity response
            $response->assertStatus(422);
        }
    });

    it('returns forbidden error when user lacks permissions', function (): void {
        $this->unauthorized_user(); // No permissions

        // Create a category
        $category = Category::factory()->create();

        // Create course items and non-course items
        $course1   = Course::factory()->create();
        $course2   = Course::factory()->create();
        $nonCourse = Product::factory()->create();

        // Attach items to category via categorizables pivot table
        $category->courses()->attach($course1);
        $category->products()->attach($nonCourse);
        $category->courses()->attach($course2);

        // Prepare payload with course item IDs
        $payload = [
            'course_ids'     => [$course1->id, $course2->id],
            'good_for_start' => true,
        ];

        // Act as user without permissions and make POST request
        $response = $this->postJson("/api/v1/admin/category/{$category->id}/good-for-start", $payload);

        // Assert 403 Forbidden response
        $response->assertStatus(403);
    });
});
