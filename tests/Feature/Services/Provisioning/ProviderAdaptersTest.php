<?php

declare(strict_types=1);

use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Services\Integrations\ImsService;
use App\Services\Integrations\MoodleService;
use App\Services\Provisioning\Providers\ImsProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider as MoodleAdapter;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider as MoodleQuizAdapter;
use App\Services\Provisioning\Providers\SkyroomProvisioningProvider;
use App\Services\Provisioning\Providers\SpotPlayerProvisioningProvider;
use App\Services\Provisioning\ProvisioningProviderRegistry;

mutates(MoodleAdapter::class);
mutates(MoodleQuizAdapter::class);
mutates(ImsProvisioningProvider::class);
mutates(SkyroomProvisioningProvider::class);
mutates(SpotPlayerProvisioningProvider::class);

function adapterEnrollment(string $provider, array $details): Enrollment
{
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        'details_json'    => $details,
    ]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [['provider' => $provider, 'applicable' => true, 'readiness' => 'ready']],
        ],
    ]);

    return $enrollment->fresh();
}

it('provisions SpotPlayer and returns canonical references', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('issueLicense')->with('SPOT-1', Mockery::type(App\Models\User::class))->andReturn([
        'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
    ]);

    expect((new SpotPlayerProvisioningProvider($service))->provision($enrollment))->toBe([
        'spot_id' => 'SPOT-1', 'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
    ]);
});

it('marks an uncertain SpotPlayer response as manual action', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('issueLicense')->andThrow(new RecoverableProvisioningException('timeout', 0, null,
        ['http_status' => 504]));

    expect(fn () => (new SpotPlayerProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'ambiguous');
});

it('rejects a SpotPlayer provider when its content reference is missing', function (): void {
    $enrollment = adapterEnrollment('spotplayer', []);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new SpotPlayerProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'spot_id');
});

it('rejects a Moodle Quiz provider that is not in the canonical plan', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['moodle_quiz_course_id' => 99]);
    $service    = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new MoodleQuizProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'not applicable');
});

it('rejects a Moodle Quiz provider when its course reference is missing', function (): void {
    $enrollment = adapterEnrollment('moodle_quiz', []);
    $service    = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new MoodleQuizProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'course_id');
});

it('rejects an IMS provider when its course reference is missing', function (): void {
    $enrollment = adapterEnrollment('ims', []);
    $service    = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new ImsProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'course code');
});

it('rejects IMS provisioning before creating a remote student when IMS is disabled', function (): void {
    $enrollment = adapterEnrollment('ims', ['ims_course_code' => 'IMS-1']);
    $service    = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->once()->andReturnFalse();
    $service->shouldNotReceive('assertConfigured');
    $service->shouldNotReceive('storeStudent');

    expect(fn () => (new ImsProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'disabled');
});

it('reports immutable paid value and the complete discount for an IMS enrollment', function (): void {
    $customer = App\Models\User::factory()->create();
    $option   = ProductDeliveryOption::factory()->create([
        'price'        => 100000,
        'details_json' => ['ims_course_code' => 'IMS-PRICE-1'],
    ]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'grand_total' => 60000,
    ]);
    $item = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option->id,
        'price'                      => 100000,
        'total'                      => 60000,
        'pricing_metadata'           => [
            'base_price_amount'     => 100000,
            'paid_amount'           => 60000,
            'total_discount_amount' => 40000,
        ],
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $item->id,
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $option->id,
    ]);
    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'amount'      => 160000,
        'status'      => App\Enums\Payment\PaymentStatusEnum::COMPLETED,
    ]);

    $service = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('storeStudent')->andReturn(['data' => ['student_id' => 7]]);
    $service->shouldReceive('storeEnrollment')->once()->withArgs(function ($user, array $payload): bool {
        return $payload['payment']['amount']          === 60000
            && $payload['payment']['discount_type']   === 'manual'
            && $payload['payment']['discount_amount'] === 40000;
    })->andReturn(['data' => ['enrollment_id' => 9]]);

    expect((new ImsProvisioningProvider($service))->provision($enrollment))
        ->toMatchArray(['course_code' => 'IMS-PRICE-1', 'ims_enrollment_id' => 9]);
});

it('resolves every provisioning provider case to its own adapter', function (): void {
    $registry = app(ProvisioningProviderRegistry::class);

    foreach (ProvisioningProviderEnum::cases() as $provider) {
        expect($registry->resolve($provider)->provider())->toBe($provider);
    }
});

it('provisions Skyroom into a staff-created room', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => 10]);
    $service    = $this->mock(SkyroomClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('findOrCreateUser')->once()->andReturn(['skyroom_user_id' => 42]);
    $service->shouldReceive('addUserToRoom')->once()->with(10, 42);

    expect((new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toBe(['room_id' => 10, 'skyroom_user_id' => 42]);
});

it('rejects a missing staff-created room as manual action', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => null]);
    $service    = $this->mock(SkyroomClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class);
});

it('rejects Skyroom provisioning before looking up a user when Skyroom is disabled', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => 10]);
    $service    = $this->mock(SkyroomClientContract::class);
    $service->shouldReceive('isEnabled')->once()->andReturnFalse();
    $service->shouldNotReceive('assertConfigured');
    $service->shouldNotReceive('findOrCreateUser');

    expect(fn () => (new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'disabled');
});

it('rejects an invalid Skyroom user reference without adding it to the room', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => 10]);
    $service    = $this->mock(SkyroomClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('findOrCreateUser')->once()->andReturn(['skyroom_user_id' => 'invalid']);
    $service->shouldNotReceive('addUserToRoom');

    expect(fn () => (new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'user reference is invalid');
});

it('rejects SpotPlayer provisioning before issuing a license when SpotPlayer is disabled', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->once()->andReturnFalse();
    $service->shouldNotReceive('assertConfigured');
    $service->shouldNotReceive('issueLicense');

    expect(fn () => (new SpotPlayerProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'disabled');
});

it('revokes Moodle access for suspended lifecycle changes', function (): void {
    $enrollment = adapterEnrollment('moodle', []);
    $enrollment->update([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'status' => 'success', 'data' => [
                        'moodle_user_id' => 42, 'moodle_course_id' => 99,
                    ],
                ],
            ],
        ],
    ]);
    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('unenrollUser')->once()->with(42, 99);

    expect((new MoodleProvisioningProvider($service))->reconcileAccess($enrollment, [
        'requested_status' => EnrollmentStatusEnum::SUSPENDED->value,
    ]))->toBe(['moodle_user_id' => 42, 'moodle_course_id' => 99]);
});

it('returns canonical Moodle course access with valid dates and ignores invalid dates', function (?string $start, ?string $end, ?int $startTime, ?int $endTime): void {
    $this->freezeTime();
    $option = ProductDeliveryOption::factory()->create(['details_json' => [
        'moodle_course_id' => '99', 'enrollment_start_date' => $start, 'enrollment_end_date' => $end,
    ]]);
    $enrollment = Enrollment::factory()->create(['product_delivery_option_id' => $option->id]);
    $client     = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->andReturnTrue();
    $client->shouldReceive('assertConfigured');
    $client->shouldReceive('findOrCreateUser')->andReturn([42, 'student']);
    $client->shouldReceive('getCourse')->with(99)->andReturn(new LmsMoodleBlockData(true, 'Course', 'https://moodle.test/course/99', false));
    $client->shouldReceive('getDefaultRoleId')->andReturn(5);
    $client->shouldReceive('enrollUser')->once()->with(42, 99, $startTime, $endTime, 5);
    $client->shouldReceive('getLoginPath')->andReturn('/login');

    expect((new MoodleAdapter($client))->provision($enrollment))->toBe([
        'moodle_user_id' => 42, 'moodle_user_name' => 'student', 'moodle_course_id' => 99,
        'course_url'     => 'https://moodle.test/course/99', 'login_path' => '/login', 'provisioned_at' => now()->toISOString(),
    ]);
})->with([
    'bounded dates' => ['2026-01-01T00:00:00Z', '2026-12-31T00:00:00Z', 1767225600, 1798675200],
    'invalid dates' => ['not a date', null, null, null],
]);

it('restores Moodle course access during active reconciliation', function (): void {
    $references = ['moodle_user_id' => 42, 'moodle_course_id' => 99];
    $enrollment = Enrollment::factory()->create(['provisioning_data' => ['providers' => ['moodle' => ['data' => $references]]]]);
    $client     = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('getDefaultRoleId')->andReturn(5);
    $client->shouldReceive('enrollUser')->once()->with(42, 99, 1767225600, null, 5);

    expect((new MoodleAdapter($client))->reconcileAccess($enrollment, [
        'requested_status' => 'active', 'access_start_date' => '2026-01-01T00:00:00Z',
    ]))->toBe($references);
});

it('rejects Moodle reconciliation without references or for an unsupported lifecycle', function (array $references, string $status, string $message): void {
    $enrollment = Enrollment::factory()->create(['provisioning_data' => ['providers' => ['moodle' => ['data' => $references]]]]);
    $client     = $this->mock(MoodleClientContract::class);

    expect(fn () => (new MoodleAdapter($client))->reconcileAccess($enrollment, ['requested_status' => $status]))
        ->toThrow(UnrecoverableProvisioningException::class, $message);
})->with([
    'missing references' => [[], 'suspended', 'references are missing'],
    'unsupported status' => [['moodle_user_id' => 42, 'moodle_course_id' => 99], 'pending', 'requires manual action'],
]);

it('returns Moodle revocation proof using the stored course references', function (): void {
    $this->freezeTime();
    $enrollment = Enrollment::factory()->create(['provisioning_data' => ['providers' => ['moodle' => [
        'data' => ['moodle_user_id' => '42', 'moodle_course_id' => '99'],
    ]]]]);
    $client = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->andReturnTrue();
    $client->shouldReceive('assertConfigured');
    $client->shouldReceive('unenrollUser')->once()->with(42, 99);

    expect((new MoodleAdapter($client))->revoke($enrollment))->toBe([
        'moodle_user_id' => 42, 'moodle_course_id' => 99, 'revoked_at' => now()->toISOString(),
    ]);
});

it('rejects Moodle quiz provisioning unless the configured provider is applicable', function (string $case): void {
    $option = ProductDeliveryOption::factory()->create(['details_json' => [
        'moodle_quiz_course_id' => $case === 'invalid course' ? 'abc' : 654,
    ]]);
    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'provisioning_plan'          => ['providers' => [['provider' => 'moodle_quiz', 'applicable' => $case !== 'inapplicable']]],
    ]);
    $enrollment->updateQuietly(['provisioning_plan' => ['providers' => [[
        'provider' => 'moodle_quiz', 'applicable' => $case !== 'inapplicable',
    ]]]]);
    $client = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->andReturn($case !== 'disabled');
    if ($case !== 'disabled') {
        $client->shouldReceive('assertConfigured');
    }

    expect(fn () => (new MoodleQuizAdapter($client))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class);
})->with(['disabled', 'inapplicable', 'invalid course']);

it('enrolls an applicable Moodle quiz user and returns only canonical references', function (): void {
    $option     = ProductDeliveryOption::factory()->create(['details_json' => ['moodle_quiz_course_id' => '654']]);
    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'provisioning_plan'          => ['providers' => [['provider' => 'moodle_quiz', 'applicable' => true]]],
    ]);
    $enrollment->updateQuietly(['provisioning_plan' => ['providers' => [[
        'provider' => 'moodle_quiz', 'applicable' => true,
    ]]]]);
    $client = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->once()->andReturnTrue();
    $client->shouldReceive('assertConfigured')->once();
    $client->shouldReceive('findOrCreateUser')->once()->withArgs(fn ($customer): bool => $customer->is($enrollment->customer))
        ->andReturn([42, 'quiz-student']);
    $client->shouldReceive('getDefaultRoleId')->once()->andReturn(5);
    $client->shouldReceive('enrollUser')->once()->with(42, 654, null, null, 5);

    expect((new MoodleQuizAdapter($client))->provision($enrollment))->toBe([
        'moodle_user_id' => 42, 'moodle_username' => 'quiz-student', 'moodle_course_id' => 654,
    ]);
});

it('requires saved user and course references to revoke Moodle quiz access', function (array $references, bool $valid): void {
    $enrollment = Enrollment::factory()->create(['provisioning_data' => ['providers' => ['moodle_quiz' => ['data' => $references]]]]);
    $client     = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->andReturnTrue();
    $client->shouldReceive('assertConfigured');
    if ($valid) {
        $client->shouldReceive('unenrollUser')->once()->with(42, 654);
    } else {
        $client->shouldNotReceive('unenrollUser');
    }

    if ($valid) {
        expect((new MoodleQuizAdapter($client))->revoke($enrollment))
            ->toMatchArray(['moodle_user_id' => 42, 'moodle_course_id' => 654]);
    } else {
        expect(fn () => (new MoodleQuizAdapter($client))->revoke($enrollment))
            ->toThrow(UnrecoverableProvisioningException::class, 'references are missing');
    }
})->with([
    'valid references'   => [['moodle_user_id' => '42', 'moodle_course_id' => '654'], true],
    'missing references' => [[], false],
]);
