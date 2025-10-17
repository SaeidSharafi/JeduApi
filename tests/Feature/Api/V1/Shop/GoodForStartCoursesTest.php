<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\Seminar;

describe('GoodForStartCoursesController', function () {
    it('returns a list of good-for-start courses for a given category', function () {
        $category = Category::factory()->create(['slug' => 'programming']);
        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();
        $seminar = Seminar::factory()->create();
        $p1 = Product::factory()
            ->withCourse($course1)
            ->withDeliveryOptions(1)
            ->create();
        $p2 = Product::factory()
            ->withCourse($course2)
            ->withDeliveryOptions(1)
            ->create();
        $p3 = Product::factory()
            ->withSeminar($seminar)
            ->withDeliveryOptions(1)
            ->create();
        $p1->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);
        $p2->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);
        $p3->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);
        $course1->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);
        $course2->categories()->syncWithPivotValues([$category], ['good_for_start' => false]);
        $seminar->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);

        $response = $this->getJson("/api/v1/shop/good-for-start/category/{$category->slug}/courses");

        $response->assertStatus(200);

        $responseData = $response->json('data');
        expect(count($responseData))->toBe(1);
    });

    it('limits the number of returned courses based on the limit parameter', closure: function () {
        $category = Category::factory()->create(['slug' => 'design']);

        Course::factory()
            ->count(10)
            ->create();
        foreach (Course::all() as $course) {
            $product = Product::factory()
                ->withCourse($course)
                ->withDeliveryOptions(1)
                ->create();
            $product->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);
            $course->categories()->syncWithPivotValues([$category], ['good_for_start' => true]);
        }
        Course::factory()
            ->count(5)
            ->create();
        Product::factory()
            ->withCourse()
            ->withDeliveryOptions(1)
            ->count(5)
            ->create();
        $response = $this->getJson("/api/v1/shop/good-for-start/category/{$category->slug}/courses");


        $response->assertStatus(200);

        $responseData = $response->json('data');
        expect(count($responseData))->toBe(10);

        $response = $this->getJson("/api/v1/shop/good-for-start/category/{$category->slug}/courses?limit=3");


        $response->assertStatus(200);

        $responseData = $response->json('data');
        expect(count($responseData))->toBe(3);
    });
});
