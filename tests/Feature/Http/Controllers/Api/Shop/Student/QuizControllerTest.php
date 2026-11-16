<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\Integrations\MoodleService;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns quizzes for authenticated user', function (): void {
    $this->customer();
    $quizzes = [
        [
            'visible'    => true,
            'name'       => 'Course A',
            'course_url' => '/course/view.php?id=101',
            'completed'  => false,
            'activities' => [
                [
                    'url'           => '/mod/quiz/view.php?id=10',
                    'cid'           => 10,
                    'name'          => 'Quiz 1',
                    'type'          => 'quiz',
                    'state'         => ['value' => 0, 'label' => 'incomplete'],
                    'grade'         => '-',
                    'timecompleted' => null,
                ],
            ],
        ],
    ];

    $this->mock(MoodleService::class, function ($mock) use ($quizzes): void {
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->with(Mockery::type(App\Models\User::class))
            ->andReturn([55, 'testuser']);
        $mock->shouldReceive('getAllQuizzes')
            ->once()
            ->with(55)
            ->andReturn($quizzes);
    });

    $response = $this->getJson(route('api.v1.shop.student.quizzes'));

    $response->assertOk();
    $response->assertJsonPath('data', $quizzes);
});

it('redacts grades from cached Moodle quizzes when a matching enrollment is survey locked', function (): void {
    $this->customer();
    $user           = $this->user;
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => 101],
        'survey_required'  => true,
        'product_id'       => Product::factory()->withCourse(),
    ]);
    createEnrollment($user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $optionalSibling = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'details_json'     => ['moodle_quiz_course_id' => 101],
        'survey_required'  => false,
        'product_id'       => $deliveryOption->product_id,
    ]);
    createEnrollment($user, DeliveryMethodEnum::IN_PERSON, deliveryOption: $optionalSibling);
    $providerOnlyOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'details_json'     => [],
        'survey_required'  => true,
        'product_id'       => $deliveryOption->product_id,
    ]);
    $providerOnlyEnrollment = createEnrollment($user, DeliveryMethodEnum::IN_PERSON, deliveryOption: $providerOnlyOption);
    $providerOnlyEnrollment->forceFill(['provisioning_data' => ['providers' => [
        'moodle_quiz' => ['data' => ['moodle_course_id' => 101]],
    ]]])->saveQuietly();
    $quizzes = [[
        'course_url'   => 'https://moodle.example.test/course/view.php?id=101',
        'course_grade' => 'A',
        'activities'   => [['cid' => 77, 'grade' => '95', 'score' => '95']],
    ]];

    $this->mock(MoodleService::class, function ($mock) use ($quizzes): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([55, 'student']);
        $mock->shouldReceive('getAllQuizzes')->once()->andReturn($quizzes);
    });

    $this->getJson(route('api.v1.shop.student.quizzes'))
        ->assertOk()
        ->assertJsonPath('data.0.course_grade', null)
        ->assertJsonPath('data.0.activities.0.grade', null)
        ->assertJsonPath('data.0.activities.0.score', null);
});

it('applies a changed survey requirement to the next read of cached quizzes', function (): void {
    $this->customer();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => 202],
        'survey_required'  => false,
        'product_id'       => Product::factory()->withCourse(),
    ]);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $quizzes = [[
        'course_url' => 'https://moodle.example.test/course/view.php?id=202',
        'activities' => [['grade' => '87']],
    ]];
    $this->mock(MoodleService::class, function ($mock) use ($quizzes): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([55, 'student']);
        $mock->shouldReceive('getAllQuizzes')->once()->andReturn($quizzes);
    });
    $route = route('api.v1.shop.student.quizzes');

    $this->getJson($route)
        ->assertOk()
        ->assertJsonPath('data.0.activities.0.grade', '87');

    $deliveryOption->update(['survey_required' => true]);

    $this->getJson($route)
        ->assertOk()
        ->assertJsonPath('data.0.activities.0.grade', null);
});

it('returns 401 for unauthenticated request', function (): void {
    $response = $this->getJson('/api/v1/shop/student/quizzes');

    $response->assertStatus(401);
});

it('handles MoodleService exception gracefully', function (): void {
    $this->customer();

    $this->mock(MoodleService::class, function ($mock): void {
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andThrow(new RuntimeException('Moodle API unavailable'));
    });

    $response = $this->getJson(route('api.v1.shop.student.quizzes'));

    expect($response->status())->toBe(500);
});

it('uses SWR cache for quiz data', function (): void {
    $this->customer();

    $quizzes = [
        [
            'visible'    => true,
            'name'       => 'Cached Course',
            'course_url' => '/course/view.php?id=1',
            'completed'  => false,
            'activities' => [],
        ],
    ];

    // Call twice — second call should not hit MoodleService again (SWR cache)
    $this->mock(MoodleService::class, function ($mock) use ($quizzes): void {
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->with(Mockery::type(App\Models\User::class))
            ->andReturn([42, 'cached_user']);
        $mock->shouldReceive('getAllQuizzes')
            ->once()
            ->with(42)
            ->andReturn($quizzes);
    });

    $this->getJson(route('api.v1.shop.student.quizzes'))->assertOk();
    $this->getJson(route('api.v1.shop.student.quizzes'))->assertOk();
});
