<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Enums\User\CivilIdTypeEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use App\Services\Integrations\ImsService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::IMS, Mockery::any())
        ->andReturn([
            'enabled'  => true,
            'base_url' => 'https://ims.test',
            'api_key'  => 'ims-key',
            'timeout'  => 15,
        ]);
    $this->imsService = app(ImsService::class);
});

it('storeStudent succeeds on 200', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['student_id' => 1001],
        ], 200),
    ]);

    $payload = [
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ];

    $response = $this->imsService->storeStudent($payload);

    expect($response['status'])->toBeTrue()
        ->and($response['message'])->toBe('ok')
        ->and(data_get($response, 'data.student_id'))->toBe(1001);

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://ims.test/api/v2/student'
            && $request->hasHeader('Authorization', 'Bearer ims-key')
            && $request->data() === $payload;
    });
});

it('storeStudent returns array on success', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'id' => 42,
        ], 200),
    ]);

    $response = $this->imsService->storeStudent([
        'first_name' => 'Jane',
    ]);

    expect($response)->toBeArray()
        ->and($response['id'])->toBe(42);
});

it('storeStudent throws with metadata on 422', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'errors' => [
                'national_code' => ['validation.unique'],
            ],
        ], 422),
    ]);

    try {
        $this->imsService->storeStudent(['national_code' => '1234567890']);
        $this->fail('Expected UnrecoverableProvisioningException');
    } catch (UnrecoverableProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(422);
        expect($e->metaData['endpoint'])->toBe('/api/v2/student');
        expect($e->metaData['validation_errors'])->toHaveKey('national_code');
        expect($e->metaData['validation_errors']['national_code'])->toContain('validation.unique');
        expect($e->metaData['raw_body_snippet'])->toBeString();
        expect(mb_strlen($e->metaData['raw_body_snippet']))->toBeLessThanOrEqual(500);
        expect($e->getMessage())->toContain('national_code:');
    }
});

it('storeStudent throws with metadata on 500', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([], 500),
    ]);

    try {
        $this->imsService->storeStudent(['national_code' => '1234567890']);
        $this->fail('Expected RecoverableProvisioningException');
    } catch (RecoverableProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(500);
        expect($e->metaData['validation_errors'])->toBe([]);
        expect($e->getMessage())->toBe('HTTP 500 on /api/v2/student.');
    }
});

it('storeEnrolment throws with metadata on 422', function (): void {
    $user = User::factory()->create([
        'civil_id'      => '1234567890',
        'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE,
    ]);

    Http::fake([
        'https://ims.test/api/v2/enrolment/*' => Http::response([
            'errors' => [
                'course_code' => ['The selected course code is invalid.'],
            ],
        ], 422),
    ]);

    try {
        $this->imsService->storeEnrolment($user, ['course_code' => 'IMS-1']);
        $this->fail('Expected UnrecoverableProvisioningException');
    } catch (UnrecoverableProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(422);
        expect($e->metaData['endpoint'])->toBe('/api/v2/enrolment');
        expect($e->metaData['validation_errors'])->toHaveKey('course_code');
    }
});

it('storeEnrolment throws with metadata on 500', function (): void {
    $user = User::factory()->create([
        'civil_id'      => '1234567890',
        'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE,
    ]);

    Http::fake([
        'https://ims.test/api/v2/enrolment/*' => Http::response([], 500),
    ]);

    try {
        $this->imsService->storeEnrolment($user, ['course_code' => 'IMS-1']);
        $this->fail('Expected RecoverableProvisioningException');
    } catch (RecoverableProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(500);
        expect($e->getMessage())->toBe('HTTP 500 on /api/v2/enrolment.');
    }
});

it('raw_body_snippet sanitizes PII', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'errors' => [
                'email'    => ['The email test@example.com has already been taken.'],
                'phone'    => ['The phone 09123456789 is invalid.'],
                'civil_id' => ['The civil_id 1234567890 must be unique.'],
            ],
        ], 422),
    ]);

    try {
        $this->imsService->storeStudent([
            'email'    => 'test@example.com',
            'phone'    => '09123456789',
            'civil_id' => '1234567890',
        ]);
        $this->fail('Expected UnrecoverableProvisioningException');
    } catch (UnrecoverableProvisioningException $e) {
        $snippet = $e->metaData['raw_body_snippet'];

        expect($snippet)->toContain('[REDACTED]');
        expect($snippet)->not->toContain('test@example.com');
        expect($snippet)->not->toContain('09123456789');
        expect($snippet)->not->toContain('1234567890');
    }
});

it('validation_errors values contain raw unsanitized messages', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'errors' => [
                'email'    => ['The email test@example.com has already been taken.'],
                'phone'    => ['The phone 09123456789 is invalid.'],
                'civil_id' => ['The civil_id 1234567890 must be unique.'],
            ],
        ], 422),
    ]);

    try {
        $this->imsService->storeStudent([
            'email'    => 'test@example.com',
            'phone'    => '09123456789',
            'civil_id' => '1234567890',
        ]);
        $this->fail('Expected UnrecoverableProvisioningException');
    } catch (UnrecoverableProvisioningException $e) {
        $errors = $e->metaData['validation_errors'];

        expect($errors['email'][0])->toBe('The email test@example.com has already been taken.')
            ->and($errors['phone'][0])->toBe('The phone 09123456789 is invalid.')
            ->and($errors['civil_id'][0])->toBe('The civil_id 1234567890 must be unique.');
    }
});

it('throws when service used before configuration', function (): void {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::IMS, Mockery::any())
        ->andReturn([]);

    $service = app(ImsService::class);

    expect(fn () => $service->storeStudent(['national_code' => '1234567890']))
        ->toThrow(UnrecoverableProvisioningException::class);
});
