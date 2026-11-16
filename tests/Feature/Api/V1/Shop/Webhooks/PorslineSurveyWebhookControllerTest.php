<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Api\Shop\Webhooks\PorslineSurveyWebhookController;
use App\Jobs\Provisioning\SyncMoodleProgressJob;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(Tests\Support\Traits\AuthTestTrait::class);
mutates(PorslineSurveyWebhookController::class);

it('completes only the token enrollment once and keeps the first timestamp on retry', function (): void {
    config()->set('services.porsline.webhook_secret', 'test-webhook-secret');
    $customer       = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id'       => Product::factory()->withCourse(),
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'survey_required'  => true,
        'survey_url'       => null,
    ]);
    $enrollment = createEnrollment($customer, DeliveryMethodEnum::IN_PERSON, deliveryOption: $deliveryOption);
    $this->customer($customer);
    $enrollment->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date' => null,
        'provisioning_data' => ['providers' => ['moodle' => ['sync' => [
            'course_grade' => 'A',
            'activities'   => [['cmid' => 7, 'name' => 'Quiz', 'type' => 'quiz', 'url' => '/quiz', 'state' => 1, 'score' => '95']],
        ]]]],
    ])->saveQuietly();
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::StudentQuizzes, ['userId' => $customer->id], ['stale']);
    $token   = $enrollment->survey_token;
    $payload = [
        'variables' => [
            ['name' => 'product_slug', 'value' => 'sample-course'],
            ['name' => 'enrollment_token', 'value' => $token],
        ],
        'questions'               => [['question' => 'How was the course?', 'answer' => 'Good']],
        'computational_variables' => [],
        'respondent_data'         => ['code' => 'response-123'],
    ];
    $headers = ['X-Survey-Webhook-Secret' => 'test-webhook-secret'];

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', $payload, $headers)
        ->assertOk()
        ->assertJsonStructure(['message', 'data']);

    $courseList = $this->getJson(route('api.v1.shop.student.courses.index'))->assertOk();
    expect(data_get($courseList->json('data.data'), '0.provisioning_data.providers.moodle.sync.course_grade'))
        ->toBe('A');

    $firstCompletion = $enrollment->fresh()->survey_completed_at;
    expect($firstCompletion)->not->toBeNull();
    expect($cache->get(CacheKey::StudentQuizzes, ['userId' => $customer->id]))->toBeNull();

    $this->travel(30)->seconds();
    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', $payload, $headers)
        ->assertOk();

    expect($enrollment->fresh()->survey_completed_at)->toBe($firstCompletion);
});

it('returns 401 and leaves completion unchanged when the webhook secret is absent or incorrect', function (bool $configured, ?string $provided): void {
    config()->set('services.porsline.webhook_secret', $configured ? 'test-webhook-secret' : null);
    $enrollment = createEnrollment(User::factory()->create(), DeliveryMethodEnum::IN_PERSON);
    $headers    = $provided === null ? [] : ['X-Survey-Webhook-Secret' => $provided];

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', surveyWebhookPayload($enrollment->survey_token), $headers)
        ->assertUnauthorized();

    expect($enrollment->fresh()->survey_completed_at)->toBeNull();
})->with([
    'missing application configuration' => [false, 'test-webhook-secret'],
    'missing header'                    => [true, null],
    'incorrect header'                  => [true, 'wrong-secret'],
]);

it('returns 422 and leaves completion unchanged for missing, ambiguous, malformed, or unknown tokens', function (string $case): void {
    config()->set('services.porsline.webhook_secret', 'test-webhook-secret');
    $enrollment = createEnrollment(User::factory()->create(), DeliveryMethodEnum::IN_PERSON);
    $token      = $enrollment->survey_token;
    $payload    = match ($case) {
        'missing'    => ['variables' => [['name' => 'product_slug', 'value' => 'course']]],
        'not a list' => ['variables' => ['entry' => ['name' => 'enrollment_token', 'value' => str_repeat('a', 64)]]],
        'ambiguous'  => ['variables' => [
            ['name' => 'enrollment_token', 'value' => $token],
            ['name' => 'enrollment_token', 'value' => $token],
        ]],
        'malformed' => ['variables' => [['name' => 'enrollment_token', 'value' => 123]]],
        'unknown'   => ['variables' => [['name' => 'enrollment_token', 'value' => str_repeat('b', 64)]]],
    };

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', $payload, [
        'X-Survey-Webhook-Secret' => 'test-webhook-secret',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variables');

    expect($enrollment->fresh()->survey_completed_at)->toBeNull();
})->with([
    'missing'    => 'missing',
    'not a list' => 'not a list',
    'ambiguous'  => 'ambiguous',
    'malformed'  => 'malformed',
    'unknown'    => 'unknown',
]);

it('allows an expired enrollment to complete without changing its access dates or status', function (): void {
    config()->set('services.porsline.webhook_secret', 'test-webhook-secret');
    $enrollment = createEnrollment(User::factory()->create(), DeliveryMethodEnum::IN_PERSON);
    $enrollment->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::EXPIRED,
        'access_start_date' => today()->subDays(20),
        'access_end_date'   => today()->subDay(),
        'revocation_status' => null,
    ])->saveQuietly();

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', surveyWebhookPayload($enrollment->survey_token), [
        'X-Survey-Webhook-Secret' => 'test-webhook-secret',
    ])->assertOk();

    expect($enrollment->fresh()->survey_completed_at)->not->toBeNull()
        ->and($enrollment->fresh()->enrollment_status)->toBe(App\Enums\EnrollmentStatusEnum::EXPIRED)
        ->and($enrollment->fresh()->access_start_date->toDateString())->toBe(today()->subDays(20)->toDateString())
        ->and($enrollment->fresh()->access_end_date->toDateString())->toBe(today()->subDay()->toDateString());
});

it('rejects survey completion before access or after revocation/refund', function (array $state): void {
    config()->set('services.porsline.webhook_secret', 'test-webhook-secret');
    $enrollment = createEnrollment(User::factory()->create(), DeliveryMethodEnum::IN_PERSON);
    $enrollment->forceFill($state)->saveQuietly();

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', surveyWebhookPayload($enrollment->survey_token), [
        'X-Survey-Webhook-Secret' => 'test-webhook-secret',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variables');

    expect($enrollment->fresh()->survey_completed_at)->toBeNull()
        ->and($enrollment->fresh()->enrollment_status)->toBe($state['enrollment_status']);
})->with([
    'future access'      => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->addDay()]],
    'awaiting payment'   => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::AWAITING_PAYMENT, 'access_start_date' => today()->subDay()]],
    'suspended'          => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::SUSPENDED, 'access_start_date' => today()->subDay()]],
    'refunded'           => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::CANCELLED, 'access_start_date' => today()->subDay()]],
    'revocation pending' => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->subDay(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::PENDING]],
    'revoked'            => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::EXPIRED, 'access_start_date' => today()->subDay(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::REVOKED]],
]);

it('completes only the token-matched Enrollment among sibling delivery options', function (): void {
    config()->set('services.porsline.webhook_secret', 'test-webhook-secret');
    $customer        = User::factory()->create();
    $product         = Product::factory()->withCourse()->create();
    $classroomOption = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'survey_required'  => true,
        'survey_url'       => 'https://survey.example.test/classroom',
    ]);
    $spotPlayerOption = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'delivery_method'  => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        'fulfillment_type' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->getFulfillmentType(),
        'survey_required'  => false,
        'survey_url'       => 'https://survey.example.test/spotplayer',
    ]);
    $classroom  = createEnrollment($customer, DeliveryMethodEnum::IN_PERSON, deliveryOption: $classroomOption);
    $spotPlayer = createEnrollment($customer, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER, deliveryOption: $spotPlayerOption);
    $this->customer($customer);
    $classroom->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date' => null,
        'provisioning_data' => ['providers' => ['moodle' => ['sync' => [
            'course_grade' => 'A',
            'activities'   => [['cmid' => 7, 'name' => 'Quiz', 'type' => 'quiz', 'url' => '/quiz', 'state' => 1, 'score' => '95']],
        ]]]],
    ])->saveQuietly();

    Queue::fake();
    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $classroom->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.results_locked', true)
        ->assertJsonPath('data.quizzes.0.score', null);

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', surveyWebhookPayload($classroom->survey_token), [
        'X-Survey-Webhook-Secret' => 'test-webhook-secret',
    ])->assertOk();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $classroom->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.results_locked', false)
        ->assertJsonPath('data.quizzes.0.score', '95');
    Queue::assertNotPushed(SyncMoodleProgressJob::class);

    expect($classroom->fresh()->survey_completed_at)->not->toBeNull()
        ->and($spotPlayer->fresh()->survey_completed_at)->toBeNull()
        ->and($classroomOption->fresh()->survey_required)->toBeTrue()
        ->and($classroomOption->fresh()->survey_url)->toBe('https://survey.example.test/classroom')
        ->and($spotPlayerOption->fresh()->survey_required)->toBeFalse()
        ->and($spotPlayerOption->fresh()->survey_url)->toBe('https://survey.example.test/spotplayer');
});

it('reveals a completed Seminar grade in the next student list response', function (): void {
    config()->set('services.porsline.webhook_secret', 'test-webhook-secret');
    $customer       = User::factory()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'product_id'       => Product::factory()->withSeminar(),
        'delivery_method'  => DeliveryMethodEnum::LIVE_SESSION_NILIROOM,
        'fulfillment_type' => DeliveryMethodEnum::LIVE_SESSION_NILIROOM->getFulfillmentType(),
        'survey_required'  => true,
        'survey_url'       => null,
    ]);
    $enrollment = createEnrollment($customer, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, deliveryOption: $deliveryOption);
    $this->customer($customer);
    $enrollment->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date' => null,
        'provisioning_data' => ['providers' => ['moodle_quiz' => ['sync' => [
            'course_grade' => 'B',
            'activities'   => [['cmid' => 8, 'name' => 'Seminar Quiz', 'type' => 'quiz', 'url' => '/quiz', 'state' => 1, 'grade' => '88']],
        ]]]],
    ])->saveQuietly();

    $this->postJson('/api/v1/shop/webhooks/porsline-surveys', surveyWebhookPayload($enrollment->survey_token), [
        'X-Survey-Webhook-Secret' => 'test-webhook-secret',
    ])->assertOk();

    $seminarList = $this->getJson(route('api.v1.shop.student.seminars.index'))->assertOk();
    expect(data_get($seminarList->json('data.data'), '0.provisioning_data.providers.moodle_quiz.sync.course_grade'))
        ->toBe('B')
        ->and(data_get($seminarList->json('data.data'), '0.provisioning_data.providers.moodle_quiz.sync.activities.0.grade'))
        ->toBe('88');
});

/** @return array<string, mixed> */
function surveyWebhookPayload(string $token): array
{
    return [
        'variables' => [
            ['name' => 'enrollment_token', 'value' => $token],
            ['name' => 'product_slug', 'value' => 'reporting-only-value'],
        ],
    ];
}
