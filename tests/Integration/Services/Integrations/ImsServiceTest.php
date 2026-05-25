<?php

declare(strict_types=1);

use App\Enums\User\CivilIdTypeEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\Integrations\ImsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->imsService = app(ImsService::class);
    $this->imsService->setConfig([
        'base_url' => 'https://ims.test',
        'api_key'  => 'ims-key',
    ]);
});

it('storeSetudent succeeds on 200', function (): void {
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

    $response = $this->imsService->storeSetudent($payload);

    expect($response['status'])->toBeTrue()
        ->and($response['message'])->toBe('ok')
        ->and(data_get($response, 'data.student_id'))->toBe(1001);

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://ims.test/api/v2/student'
            && $request->hasHeader('Authorization', 'Bearer ims-key')
            && $request->data() === $payload;
    });
});

it('storeSetudent returns array on success', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'id' => 42,
        ], 200),
    ]);

    $response = $this->imsService->storeSetudent([
        'first_name' => 'Jane',
    ]);

    expect($response)->toBeArray()
        ->and($response['id'])->toBe(42);
});

it('storeSetudent throws with metadata on 422', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([
            'errors' => [
                'national_code' => ['validation.unique'],
            ],
        ], 422),
    ]);

    try {
        $this->imsService->storeSetudent(['national_code' => '1234567890']);
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(422);
        expect($e->metaData['endpoint'])->toBe('/api/v2/student');
        expect($e->metaData['validation_errors'])->toHaveKey('national_code');
        expect($e->metaData['validation_errors']['national_code'])->toContain('validation.unique');
        expect($e->metaData['raw_body_snippet'])->toBeString();
        expect(mb_strlen($e->metaData['raw_body_snippet']))->toBeLessThanOrEqual(500);
        expect($e->getMessage())->toContain('national_code:');
    }
});

it('storeSetudent throws with metadata on 500', function (): void {
    Http::fake([
        'https://ims.test/api/v2/student' => Http::response([], 500),
    ]);

    try {
        $this->imsService->storeSetudent(['national_code' => '1234567890']);
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(500);
        expect($e->metaData['validation_errors'])->toBe([]);
        expect($e->getMessage())->toBe('IMS provisioning request failed.');
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
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(422);
        expect($e->metaData['endpoint'])->toBe('/api/v2/enrolment/{civil_id}');
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
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        expect($e->metaData['http_status'])->toBe(500);
        expect($e->getMessage())->toBe('IMS enrolment creation request failed.');
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
        $this->imsService->storeSetudent([
            'email'    => 'test@example.com',
            'phone'    => '09123456789',
            'civil_id' => '1234567890',
        ]);
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        $snippet = $e->metaData['raw_body_snippet'];

        expect($snippet)->toContain('[REDACTED]');
        expect($snippet)->not->toContain('test@example.com');
        expect($snippet)->not->toContain('09123456789');
        expect($snippet)->not->toContain('1234567890');
    }
});

it('validation_errors values are sanitized for PII', function (): void {
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
        $this->imsService->storeSetudent([
            'email'    => 'test@example.com',
            'phone'    => '09123456789',
            'civil_id' => '1234567890',
        ]);
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        $errors = $e->metaData['validation_errors'];

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                expect($message)->toContain('[REDACTED]');
            }
        }

        $allMessages = json_encode($errors);
        expect($allMessages)->not->toContain('test@example.com');
        expect($allMessages)->not->toContain('09123456789');
        expect($allMessages)->not->toContain('1234567890');
    }
});

it('endpoint metadata does not contain civil_id', function (): void {
    $user = User::factory()->create([
        'civil_id'      => '9876543210',
        'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE,
    ]);

    Http::fake([
        'https://ims.test/api/v2/enrolment/*' => Http::response([], 422),
    ]);

    try {
        $this->imsService->storeEnrolment($user, ['course_code' => 'IMS-1']);
        $this->fail('Expected ExternalProvisioningException');
    } catch (ExternalProvisioningException $e) {
        expect($e->metaData['endpoint'])->toBe('/api/v2/enrolment/{civil_id}');
        expect($e->metaData['endpoint'])->not->toContain('9876543210');
    }
});

it('throws when service used before configuration', function (): void {
    $service = app(ImsService::class);

    expect(fn () => $service->storeSetudent(['national_code' => '1234567890']))
        ->toThrow(ExternalProvisioningException::class, 'IMS service configuration is missing.');
});
