<?php

declare(strict_types=1);

use App\Actions\Shop\Student\GetEnrollmentDetailAction;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Api\Shop\Student\CourseController;
use App\Jobs\Provisioning\SyncMoodleProgressJob;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Product;

uses(Tests\Support\Traits\AuthTestTrait::class);

mutates(GetEnrollmentDetailAction::class, CourseController::class);

beforeEach(function (): void {
    $this->customer();
});

// ─── Ownership ───────────────────────────────────────────────────────────────

it('returns 404 for non-owner enrollment on show', function (): void {
    $other      = App\Models\User::factory()->create();
    $enrollment = createEnrollment($other, DeliveryMethodEnum::LMS_MOODLE);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});

it('returns enrollment detail for owner', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.uuid', $enrollment->uuid);
});

it('locks course results while a required survey is still being prepared', function (): void {
    $course         = Course::factory()->create(['provides_certificate' => true]);
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'product_id'       => Product::factory()->create([
            'productable_type' => $course->getMorphClass(),
            'productable_id'   => $course->id,
        ])->id,
        'survey_required' => true,
        'survey_url'      => null,
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill(['provisioning_data' => ['providers' => ['moodle' => ['sync' => [
        'course_grade' => 'A',
        'activities'   => [['cmid' => 7, 'name' => 'Quiz', 'type' => 'quiz', 'url' => '/quiz', 'state' => 1, 'score' => '95']],
    ]]]]])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'awaiting_survey')
        ->assertJsonPath('data.survey_block.results_locked', true)
        ->assertJsonPath('data.survey_block.message', __('messages.enrollments.survey_awaiting'))
        ->assertJsonPath('data.delivery_access.course_grade', null)
        ->assertJsonPath('data.quizzes.0.score', null)
        ->assertJsonPath('data.certificate_info.is_available', false)
        ->assertJsonPath('data.certificate_info.certificate_url', null);
});

it('returns the exact delivery option survey state and unlocks stored results after completion', function (): void {
    $course         = Course::factory()->create(['provides_certificate' => true]);
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'product_id'       => Product::factory()->create([
            'productable_type' => $course->getMorphClass(),
            'productable_id'   => $course->id,
        ])->id,
        'survey_required' => true,
        'survey_url'      => null,
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'survey_completed_at' => now(),
        'enrollment_status'   => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date'   => null,
        'revocation_status'   => null,
        'provisioning_data'   => ['providers' => ['moodle' => ['sync' => [
            'course_grade' => 'A',
            'activities'   => [['cmid' => 7, 'name' => 'Quiz', 'type' => 'quiz', 'url' => '/quiz', 'state' => 1, 'score' => '95']],
        ]]]],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'completed')
        ->assertJsonPath('data.survey_block.results_locked', false)
        ->assertJsonPath('data.delivery_access.course_grade', 'A')
        ->assertJsonPath('data.quizzes.0.score', '95')
        ->assertJsonPath('data.certificate_info.is_available', true);
});

it('resolves survey state and URL independently for sibling delivery options', function (): void {
    $product        = Product::factory()->withCourse()->create();
    $awaitingOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'product_id'       => $product->id,
        'survey_required'  => true,
        'survey_url'       => null,
    ]);
    $pendingOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'product_id'       => $product->id,
        'survey_required'  => true,
        'survey_url'       => 'https://survey.example.test/online',
    ]);
    $awaiting = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON, deliveryOption: $awaitingOption);
    $pending  = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $pendingOption);
    $pending->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date' => today(),
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $awaiting->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'awaiting_survey')
        ->assertJsonPath('data.survey_block.url', null);
    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $pending->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'pending')
        ->assertJsonPath('data.survey_block.results_locked', true)
        ->assertJsonPath('data.survey_block.url', fn (?string $url): bool => str_starts_with($url ?? '', 'https://survey.example.test/online?'));
});

it('keeps configured survey state pending when access suppresses the link', function (array $accessState): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'product_id'       => Product::factory()->withCourse(),
        'survey_required'  => true,
        'survey_url'       => 'https://survey.example.test/restricted',
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill($accessState)->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'pending')
        ->assertJsonPath('data.survey_block.results_locked', true)
        ->assertJsonPath('data.survey_block.url', null)
        ->assertJsonPath('data.survey_block.message', null);
})->with([
    'future access'    => ['accessState' => ['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->addDay()]],
    'awaiting payment' => ['accessState' => ['enrollment_status' => App\Enums\EnrollmentStatusEnum::AWAITING_PAYMENT, 'access_start_date' => today()]],
    'suspended'        => ['accessState' => ['enrollment_status' => App\Enums\EnrollmentStatusEnum::SUSPENDED, 'access_start_date' => today()]],
    'revoked'          => ['accessState' => ['enrollment_status' => App\Enums\EnrollmentStatusEnum::CANCELLED, 'access_start_date' => today(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::REVOKED]],
]);

it('returns a stable encoded bearer link for the exact PDO after access starts', function (): void {
    $course = Course::factory()->create([
        'full_name' => 'دوره & پیشرفته',
    ]);
    $product = Product::factory()->create([
        'name'             => 'نام محصول + سطح دو',
        'slug'             => 'product-canonical-slug',
        'productable_type' => $course->getMorphClass(),
        'productable_id'   => $course->id,
    ]);
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'product_id'      => $product->id,
        'survey_required' => true,
        'survey_url'      => 'https://survey.example.test/form?utm_source=mail&enrollment_token=forged&product_slug=wrong-slug&product_name=wrong&course_name=wrong&product_type=seminar#finish',
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::EXPIRED,
        'access_start_date' => today()->subDays(3),
        'access_end_date'   => today()->subDay(),
        'revocation_status' => null,
    ])->saveQuietly();
    expect(filled($enrollment->fresh()->survey_token))->toBeTrue();
    $enrollment->forceFill(['survey_token' => null, 'survey_token_digest' => null])->saveQuietly();

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'pending');
    $url   = $response->json('data.survey_block.url');
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    $stored = $enrollment->fresh();

    expect($parts['fragment'] ?? null)->toBe('finish')
        ->and($query['utm_source'] ?? null)->toBe('mail')
        ->and($query['product_slug'] ?? null)->toBe('product-canonical-slug')
        ->and($query['product_name'] ?? null)->toBe('نام محصول + سطح دو')
        ->and($query['course_name'] ?? null)->toBe('دوره & پیشرفته')
        ->and($query['product_type'] ?? null)->toBe('course')
        ->and($query['enrollment_token'] ?? null)->not->toBe('forged')
        ->and($query['enrollment_token'] ?? '')->toMatch('/^[a-f0-9]{64}$/')
        ->and(array_values($query))->not->toContain((string) $enrollment->id)
        ->and($url)->not->toContain($enrollment->uuid)
        ->and(array_values($query))->not->toContain((string) $deliveryOption->id)
        ->and(array_values($query))->not->toContain((string) $product->id);
    expect(hash_equals((string) $stored->survey_token, (string) ($query['enrollment_token'] ?? '')))->toBeTrue()
        ->and($stored->survey_token_digest === hash('sha256', (string) $stored->survey_token))->toBeTrue()
        ->and($stored->toArray())->not->toHaveKey('survey_token')
        ->and($stored->toArray())->not->toHaveKey('survey_token_digest');

    $enrollment->productDeliveryOption->product->update(['name' => 'renamed product', 'slug' => 'renamed-slug']);
    $enrollment->productDeliveryOption->update(['survey_url' => 'https://survey.example.test/replaced?source=latest']);
    $again = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()->json('data.survey_block.url');
    parse_str(parse_url($again, PHP_URL_QUERY) ?: '', $againQuery);

    expect($againQuery['enrollment_token'] ?? null)->toBe($query['enrollment_token'])
        ->and($againQuery['product_slug'] ?? null)->toBe('renamed-slug')
        ->and($againQuery['product_name'] ?? null)->toBe('renamed product')
        ->and($againQuery['source'] ?? null)->toBe('latest')
        ->and(parse_url($again, PHP_URL_PATH))->toBe('/replaced');
});

it('supports a shared survey URL across PDOs with distinct enrollment tokens', function (): void {
    $product         = Product::factory()->withCourse()->create();
    $deliveryOptions = collect([DeliveryMethodEnum::IN_PERSON, DeliveryMethodEnum::LMS_MOODLE])
        ->map(fn (DeliveryMethodEnum $method): App\Models\ProductDeliveryOption => App\Models\ProductDeliveryOption::factory()->create([
            'product_id'       => $product->id,
            'delivery_method'  => $method,
            'fulfillment_type' => $method->getFulfillmentType(),
            'survey_required'  => true,
            'survey_url'       => 'https://survey.example.test/shared?utm_campaign=fall',
        ]));
    $enrollments = $deliveryOptions->map(function (App\Models\ProductDeliveryOption $deliveryOption): Enrollment {
        $enrollment = createEnrollment($this->user, $deliveryOption->delivery_method, deliveryOption: $deliveryOption);
        $enrollment->forceFill([
            'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
            'access_start_date' => today(),
        ])->saveQuietly();

        return $enrollment;
    });

    $tokens = $enrollments->map(function (Enrollment $enrollment): string {
        $url = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
            ->assertOk()->json('data.survey_block.url');
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);

        expect(parse_url($url, PHP_URL_PATH))->toBe('/shared')
            ->and($query['utm_campaign'] ?? null)->toBe('fall');

        return (string) ($query['enrollment_token'] ?? '');
    });

    expect($tokens->unique()->count())->toBe(2);
});

it('provides an optional survey link without applying the results gate', function (): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'product_id'       => Product::factory()->withCourse(),
        'survey_required'  => false,
        'survey_url'       => 'https://survey.example.test/optional?source=demo',
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date' => today(),
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'not_required')
        ->assertJsonPath('data.survey_block.results_locked', false)
        ->assertJsonPath('data.survey_block.url', fn (?string $url): bool => str_starts_with($url ?? '', 'https://survey.example.test/optional?source=demo&'));
});

it('uses the Seminar product and readable name in its survey link', function (): void {
    $seminar = App\Models\Seminar::factory()->create(['full_name' => 'Seminar readable name']);
    $product = Product::factory()->create([
        'name'             => 'Seminar catalog product',
        'slug'             => 'seminar-product-slug',
        'productable_type' => $seminar->getMorphClass(),
        'productable_id'   => $seminar->id,
    ]);
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'product_id'      => $product->id,
        'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_NILIROOM,
        'survey_required' => true,
        'survey_url'      => 'https://survey.example.test/seminar',
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date' => today(),
    ])->saveQuietly();

    $response = $this->getJson(route('api.v1.shop.student.seminars.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();
    parse_str(parse_url($response->json('data.survey_block.url'), PHP_URL_QUERY) ?: '', $query);

    expect($query['product_slug'] ?? null)->toBe('seminar-product-slug')
        ->and($query['product_name'] ?? null)->toBe('Seminar catalog product')
        ->and($query['course_name'] ?? null)->toBe('Seminar readable name')
        ->and($query['product_type'] ?? null)->toBe('seminar');
});

it('does not provide a survey link before access starts or for restricted enrollments', function (array $attributes): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'product_id'       => Product::factory()->withCourse(),
        'survey_required'  => true,
        'survey_url'       => 'https://survey.example.test/form',
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill($attributes)->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.url', null);
})->with([
    'future access'      => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->addDay()]],
    'awaiting payment'   => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::AWAITING_PAYMENT, 'access_start_date' => today()->subDay()]],
    'suspended'          => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::SUSPENDED, 'access_start_date' => today()->subDay()]],
    'cancelled'          => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::CANCELLED, 'access_start_date' => today()->subDay()]],
    'revoked'            => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::EXPIRED, 'access_start_date' => today()->subDay(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::REVOKED]],
    'revocation pending' => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->subDay(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::PENDING]],
]);

it('treats an optional survey as not required even when it has no URL or completion', function (): void {
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'product_id'       => Product::factory()->withCourse(),
        'survey_required'  => false,
        'survey_url'       => null,
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON, deliveryOption: $deliveryOption);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'not_required')
        ->assertJsonPath('data.survey_block.results_locked', false)
        ->assertJsonPath('data.survey_block.url', null);
});

// ─── Delivery access ─────────────────────────────────────────────────────────

it('show returns moodle delivery_access for lms_moodle enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, provisioning: true);

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['delivery_access', 'files', 'quizzes']]);
    $access = $response->json('data.delivery_access');
    expect($access)
        ->toHaveKey('type')
        ->and($access)->toHaveKey('is_ready')
        ->and($access)->toHaveKey('course_url')
        ->and($access)->toHaveKey('completed')
        ->and($access)->toHaveKey('course_grade');
});

it('show returns spotplayer delivery_access for video_platform_spotplayer enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER);

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['delivery_access', 'files', 'quizzes']]);
    $access = $response->json('data.delivery_access');
    expect($access)->toHaveKey('type')
        ->and($access)->toHaveKey('is_ready')
        ->and($access)->toHaveKey('license_key')
        ->and($access)->toHaveKey('player_url');
});

it('show returns in_person delivery_access for in_person enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['delivery_access', 'files', 'quizzes']]);
    $access = $response->json('data.delivery_access');
    expect($access)->toHaveKey('type')
        ->and($access)->toHaveKey('is_ready')
        ->and($access)->toHaveKey('address')
        ->and($access)->toHaveKey('map_url');
});

it('show returns direct_download delivery_access for direct_download enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD);

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['delivery_access', 'files', 'quizzes']]);
    $access = $response->json('data.delivery_access');
    expect($access)->toHaveKey('type');
});

// ─── certificate_info ────────────────────────────────────────────────────────

it('certificate_info is null when productable does not provide certificate', function (): void {
    // DigitalAsset has no provides_certificate; Course factory has it as random bool
    // Force a Course with provides_certificate = false
    $course     = Course::factory()->create(['provides_certificate' => false]);
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    expect($response->json('data.certificate_info.is_available'))->toBeFalse()
        ->and($response->json('data.certificate_info.provides_certificate'))->toBeFalse()
        ->and($response->json('data.certificate_info.certificate_url'))->toBeNull();
});

it('reports seminar certificate capability independently of availability', function (bool $providesCertificate): void {
    $seminar    = App\Models\Seminar::factory()->create(['provides_certificate' => $providesCertificate]);
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, $seminar);

    $this->getJson(route('api.v1.shop.student.seminars.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.certificate_info.provides_certificate', $providesCertificate)
        ->assertJsonPath('data.certificate_info.is_available', false);
})->with([
    'supports certificates'         => [true],
    'does not support certificates' => [false],
]);

it('certificate_info is_available false when survey_completed_at is null', function (): void {
    $course     = Course::factory()->create(['provides_certificate' => true]);
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);
    $enrollment->productDeliveryOption->forceFill(['survey_required' => true, 'survey_url' => null])->save();
    $enrollment->survey_completed_at = null;
    $enrollment->save();

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    expect($response->json('data.certificate_info.provides_certificate'))->toBeTrue()
        ->and($response->json('data.certificate_info.is_available'))->toBeFalse();
});

it('certificate_info is_available true when survey_completed_at is set', function (): void {
    $course     = Course::factory()->create(['provides_certificate' => true]);
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);
    $enrollment->forceFill([
        'survey_completed_at' => now(),
        'enrollment_status'   => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date'   => null,
        'revocation_status'   => null,
    ])->saveQuietly();

    $response = $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk();
    expect($response->json('data.certificate_info.provides_certificate'))->toBeTrue()
        ->and($response->json('data.certificate_info.is_available'))->toBeTrue();
});

it('keeps certificate information unavailable after survey completion when the enrollment is not eligible', function (array $state): void {
    $course     = Course::factory()->create(['provides_certificate' => true]);
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);
    $enrollment->forceFill([
        'survey_completed_at' => now(),
        ...$state,
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.certificate_info.is_available', false)
        ->assertJsonPath('data.certificate_info.provides_certificate', true)
        ->assertJsonPath('data.certificate_info.certificate_url', null);
})->with([
    'future access'      => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->addDay(), 'revocation_status' => null]],
    'awaiting payment'   => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::AWAITING_PAYMENT, 'access_start_date' => null, 'revocation_status' => null]],
    'suspended'          => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::SUSPENDED, 'access_start_date' => today()->subDay(), 'revocation_status' => null]],
    'refunded'           => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::CANCELLED, 'access_start_date' => today()->subDay(), 'revocation_status' => null]],
    'revocation pending' => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::ACTIVE, 'access_start_date' => today()->subDay(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::PENDING]],
    'revoked'            => [['enrollment_status' => App\Enums\EnrollmentStatusEnum::EXPIRED, 'access_start_date' => today()->subDay(), 'revocation_status' => App\Enums\EnrollmentRevocationStatusEnum::REVOKED]],
]);

it('keeps certificate availability for an active enrollment with no start date when its survey is optional', function (): void {
    $course         = Course::factory()->create(['provides_certificate' => true]);
    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'product_id'      => Product::factory()->for($course, 'productable')->create()->id,
        'survey_required' => false,
        'survey_url'      => null,
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'enrollment_status'   => App\Enums\EnrollmentStatusEnum::ACTIVE,
        'access_start_date'   => null,
        'survey_completed_at' => null,
        'revocation_status'   => null,
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.survey_block.state', 'not_required')
        ->assertJsonPath('data.certificate_info.is_available', true)
        ->assertJsonPath('data.certificate_info.certificate_url', null);
});

// ─── SWR dispatch ────────────────────────────────────────────────────────────

it('show dispatches SyncMoodleProgressJob (rate-limited) for provisioned moodle enrollment', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $moodleCourseId = 999;
    $moodleUserId   = 888;

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => $moodleCourseId],
        'product_id'       => Product::factory()->withCourse(),
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'moodle_user_id' => $moodleUserId,
                        'course_info'    => [
                            'visible'    => true,
                            'name'       => 'Test Course',
                            'course_url' => null,
                            'completed'  => false,
                        ],
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertPushed(SyncMoodleProgressJob::class);
});

it('show dispatches SyncMoodleProgressJob for moodle_quiz enrollment with provider key', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $moodleCourseId = 777;
    $moodleUserId   = 666;

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON->value,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        'details_json'     => ['moodle_quiz_course_id' => $moodleCourseId],
        'product_id'       => Product::factory()->withCourse(),
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle_quiz' => [
                    'data' => [
                        'moodle_user_id' => $moodleUserId,
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertPushed(SyncMoodleProgressJob::class, function (SyncMoodleProgressJob $job) use ($enrollment, $moodleCourseId, $moodleUserId): bool {
        $reflection   = new ReflectionClass($job);
        $enrollmentId = $reflection->getProperty('enrollmentId');
        $moodleCourse = $reflection->getProperty('moodleCourseId');
        $moodleUser   = $reflection->getProperty('moodleUserId');
        $providerKey  = $reflection->getProperty('providerKey');

        $enrollmentId->setAccessible(true);
        $moodleCourse->setAccessible(true);
        $moodleUser->setAccessible(true);
        $providerKey->setAccessible(true);

        return $enrollmentId->getValue($job) === $enrollment->id
            && $moodleCourse->getValue($job) === $moodleCourseId
            && $moodleUser->getValue($job)   === $moodleUserId
            && $providerKey->getValue($job)  === 'moodle_quiz';
    });
});

it('show does not dispatch SWR job for non-moodle enrollment', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertNotPushed(SyncMoodleProgressJob::class);
});

it('show does not dispatch SWR job for moodle enrollment without provisioning data', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => 777],
        'product_id'       => Product::factory()->withCourse(),
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    // Has course_info so show doesn't 500, but moodle_user_id is absent so SWR is skipped
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'course_info' => [
                            'visible'    => true,
                            'name'       => 'Test Course',
                            'course_url' => null,
                            'completed'  => false,
                        ],
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertNotPushed(SyncMoodleProgressJob::class);
});

it('show does not dispatch SyncMoodleProgressJob twice within decay window', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $moodleCourseId = 999;
    $moodleUserId   = 888;

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => $moodleCourseId],
        'product_id'       => Product::factory()->withCourse(),
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'moodle_user_id' => $moodleUserId,
                        'course_info'    => [
                            'visible'    => true,
                            'name'       => 'Test Course',
                            'course_url' => null,
                            'completed'  => false,
                        ],
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $url = route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]);

    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertOk();

    Illuminate\Support\Facades\Queue::assertPushed(SyncMoodleProgressJob::class, 1);
});

it('show dispatches SyncMoodleProgressJob when the moodle_quiz course id is a numeric string', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON->value,
        'fulfillment_type' => DeliveryMethodEnum::IN_PERSON->getFulfillmentType(),
        // details_json is an untyped JSON column, so an admin-entered id may arrive as a string.
        'details_json' => ['moodle_quiz_course_id' => '777'],
        'product_id'   => Product::factory()->withCourse(),
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle_quiz' => [
                    'data' => [
                        'moodle_user_id' => 666,
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertPushed(SyncMoodleProgressJob::class);
});

it('show does not dispatch SyncMoodleProgressJob when the moodle course id is not positive', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE->value,
        'fulfillment_type' => DeliveryMethodEnum::LMS_MOODLE->getFulfillmentType(),
        'details_json'     => ['moodle_course_id' => 0],
        'product_id'       => Product::factory()->withCourse(),
    ]);

    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'data' => [
                        'moodle_user_id' => 888,
                    ],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk();

    Illuminate\Support\Facades\Queue::assertNotPushed(SyncMoodleProgressJob::class);
});

// ─── Review info ─────────────────────────────────────────────────────────────

it('reports the review in review_info for a live review', function (App\Enums\Content\ReviewStatusEnum $status): void {
    $course     = Course::factory()->create();
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);

    App\Models\Review::factory()->create([
        'user_id'         => $this->user->id,
        'reviewable_type' => $course->getMorphClass(),
        'reviewable_id'   => $course->id,
        'rating'          => 4,
        'title'           => 'Good course',
        'comment'         => 'Learned a lot.',
        'status'          => $status,
    ]);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.review_info.has_reviewed', true)
        ->assertJsonPath('data.review_info.review.rating', 4)
        ->assertJsonPath('data.review_info.review.title', 'Good course')
        ->assertJsonPath('data.review_info.review.comment', 'Learned a lot.')
        ->assertJsonPath('data.review_info.review.status.value', $status->value);
})->with([
    App\Enums\Content\ReviewStatusEnum::PENDING,
    App\Enums\Content\ReviewStatusEnum::APPROVED,
]);

it('reports no review in review_info after the review was rejected', function (): void {
    $course     = Course::factory()->create();
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);

    App\Models\Review::factory()->create([
        'user_id'         => $this->user->id,
        'reviewable_type' => $course->getMorphClass(),
        'reviewable_id'   => $course->id,
        'status'          => App\Enums\Content\ReviewStatusEnum::REJECTED,
    ]);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.review_info.has_reviewed', false)
        ->assertJsonPath('data.review_info.review', null);
});

it('does not report another customer review in review_info', function (): void {
    $course     = Course::factory()->create();
    $enrollment = createEnrollmentForProductable($this->user, DeliveryMethodEnum::LMS_MOODLE, $course);

    App\Models\Review::factory()->create([
        'user_id'         => App\Models\User::factory()->create()->id,
        'reviewable_type' => $course->getMorphClass(),
        'reviewable_id'   => $course->id,
        'status'          => App\Enums\Content\ReviewStatusEnum::APPROVED,
    ]);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.review_info.has_reviewed', false)
        ->assertJsonPath('data.review_info.review', null);
});

// ─── Removed route ───────────────────────────────────────────────────────────

it('moodle update patch route returns 404', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $this->patchJson("/api/v1/shop/my-courses/{$enrollment->uuid}/moodle/update")
        ->assertNotFound();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Create enrollment with a specific productable already attached to the product.
 */
function createEnrollmentForProductable(
    App\Models\User $customer,
    DeliveryMethodEnum $deliveryMethod,
    mixed $productable,
): Enrollment {
    $product = Product::factory()->create([
        'productable_type' => $productable->getMorphClass(),
        'productable_id'   => $productable->id,
    ]);

    $deliveryOption = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => $deliveryMethod->value,
        'fulfillment_type' => $deliveryMethod->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);

    return createEnrollment($customer, $deliveryMethod, deliveryOption: $deliveryOption);
}
