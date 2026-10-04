<?php

declare(strict_types=1);

use App\Actions\Shop\Organization\CreateOrganizationTrainingRequestAction;
use App\Actions\Shop\UploadFileAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Shop\Organization\OrganizationTrainingRequestCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\InboundRequestStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Models\Course;
use App\Models\OrganizationPage;
use App\Models\OrganizationTrainingRequest;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Staff;
use App\Models\Vendor;
use App\Notifications\Admin\OrganizationTrainingRequestSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('creates a public request with normalized course names and notifies authorized staff', function (): void {
    $vendor = Vendor::factory()->create(['name' => 'Original Department']);
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    $authorized = Staff::factory()->create();
    $authorized->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW_ANY->value);
    $unauthorized = Staff::factory()->create();
    Notification::fake();

    $response = $this->postJson(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => [' Project management ', 'Project   management', 'Leadership'],
        'notes'                  => 'Please contact us in the morning.',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['reference'], 'metadata'])
        ->assertJsonPath('data.reference', fn (mixed $reference): bool => is_string($reference) && $reference !== '');

    $request = OrganizationTrainingRequest::query()->firstOrFail();
    expect($request->status)->toBe(InboundRequestStatusEnum::PENDING)
        ->and($request->requested_course_names)->toBe(['Project management', 'Leadership'])
        ->and($request->vendor_id)->toBe($vendor->id)
        ->and($request->vendor_snapshot)->toBe(['id' => $vendor->id, 'name' => 'Original Department'])
        ->and($request->assigned_to_id)->toBeNull();

    Notification::assertSentTo($authorized, OrganizationTrainingRequestSubmittedNotification::class, function (OrganizationTrainingRequestSubmittedNotification $notification) use ($request, $authorized): bool {
        $payload = $notification->toDatabase($authorized);

        return $payload['resource_type'] === 'organization_training_request'
            && $payload['resource_id']   === $request->id;
    });
    Notification::assertNotSentTo($unauthorized, OrganizationTrainingRequestSubmittedNotification::class);
});

it('accepts a two megabyte PDF and exposes it only through an authorized download', function (): void {
    Storage::fake('local');
    Notification::fake();

    $response = $this->post(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => [],
        'attachment'             => UploadedFile::fake()->create('requirements.pdf', 2048, 'application/pdf'),
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['reference']])
        ->assertJsonMissingPath('data.attachment');

    $request = OrganizationTrainingRequest::query()->firstOrFail();
    $media   = $request->firstMedia('attachment');
    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('local');
    Storage::disk('local')->assertExists($media->getDiskPath());

    $this->authorized_user([
        PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW,
        PermissionEnum::FILE_VIEW_ANY,
    ]);
    $show = $this->getJson(route('api.v1.admin.organization-training-requests.show', $request));
    $show->assertOk()->assertJsonPath('data.attachment.url', route(
        'api.v1.admin.private-upload.download',
        ['file' => $media->id],
    ));

    $this->get($show->json('data.attachment.url'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->user = Staff::factory()->create();
    $this->unauthorized_user();
    $this->get(route('api.v1.admin.private-upload.download', ['file' => $media->id]))
        ->assertForbidden();
});

it('accepts manual course names together with a valid PDF', function (): void {
    Storage::fake('local');
    Notification::fake();

    $response = $this->post(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => ['Leadership'],
        'attachment'             => UploadedFile::fake()->create('requirements.pdf', 10, 'application/pdf'),
    ]);

    $response->assertCreated();

    $request = OrganizationTrainingRequest::query()->firstOrFail();
    expect($request->requested_course_names)->toBe(['Leadership'])
        ->and($request->firstMedia('attachment'))->not->toBeNull();
});

it('rejects missing required fields and leaves no request or notification', function (): void {
    Notification::fake();

    $response = $this->postJson(route('api.v1.shop.organization.training-requests.store'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'first_name',
            'last_name',
            'phone',
            'position',
            'organization_name',
            'requested_course_names',
        ])
        ->assertJsonPath('errors.requested_course_names.0', 'At least one requested course name or a PDF attachment is required.');
    $this->assertDatabaseCount('organization_training_requests', 0);
    Notification::assertNothingSent();
});

it('rejects course IDs without manual names or an attachment', function (): void {
    Notification::fake();

    $response = $this->postJson(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'        => 'Sara',
        'last_name'         => 'Ahmadi',
        'phone'             => '09121234567',
        'position'          => 'HR manager',
        'organization_name' => 'Example Organization',
        'course_ids'        => [1, 2],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('requested_course_names')
        ->assertJsonPath('errors.requested_course_names.0', 'At least one requested course name or a PDF attachment is required.');
    $this->assertDatabaseCount('organization_training_requests', 0);
    Notification::assertNothingSent();
});

it('rejects non-PDF attachments without persisting a request', function (): void {
    Notification::fake();

    $response = $this->post(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => ['Leadership'],
        'attachment'             => UploadedFile::fake()->create('requirements.txt', 10, 'text/plain'),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('attachment')
        ->assertJsonPath('errors.attachment.0', 'The attachment field must be a file of type: pdf.');
    $this->assertDatabaseCount('organization_training_requests', 0);
    Notification::assertNothingSent();
});

it('rejects attachments larger than two megabytes', function (): void {
    Notification::fake();

    $response = $this->post(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => ['Leadership'],
        'attachment'             => UploadedFile::fake()->create('requirements.pdf', 2049, 'application/pdf'),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('attachment')
        ->assertJsonPath('errors.attachment.0', 'The attachment field must not be greater than 2048 kilobytes.');
    $this->assertDatabaseCount('organization_training_requests', 0);
    Notification::assertNothingSent();
});

it('rolls back the request when attachment storage fails', function (): void {
    Notification::fake();
    $this->mock(UploadFileAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Storage unavailable'));
    });

    $data = OrganizationTrainingRequestCreateData::from([
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => ['Leadership'],
        'attachment'             => UploadedFile::fake()->create('requirements.pdf', 10, 'application/pdf'),
    ]);

    expect(fn () => app(CreateOrganizationTrainingRequestAction::class)->handle($data))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('organization_training_requests', 0);
    Notification::assertNothingSent();
});

it('lists requests and lets authorized staff update status and assignment', function (): void {
    $this->authorized_user([
        PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW_ANY,
        PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW,
        PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE,
    ]);
    $request  = OrganizationTrainingRequest::factory()->create();
    $assignee = Staff::factory()->create();

    $this->getJson(route('api.v1.admin.organization-training-requests.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['data' => [['id', 'reference', 'organization_name', 'status', 'assignee']]]]);

    $this->patchJson(route('api.v1.admin.organization-training-requests.update-status', $request), [
        'status' => InboundRequestStatusEnum::CONTACTED->value,
    ])->assertOk();
    $this->patchJson(route('api.v1.admin.organization-training-requests.update-assignment', $request), [
        'staff_id' => $assignee->id,
    ])->assertOk();

    $this->assertDatabaseHas('organization_training_requests', [
        'id'             => $request->id,
        'status'         => InboundRequestStatusEnum::CONTACTED->value,
        'assigned_to_id' => $assignee->id,
    ]);
});

it('does not expose request data to staff with update permission only', function (): void {
    $this->authorized_user([PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE]);
    $request = OrganizationTrainingRequest::factory()->create([
        'phone' => '09121234567',
        'notes' => 'Private notes',
    ]);

    $response = $this->patchJson(route('api.v1.admin.organization-training-requests.update-status', $request), [
        'status' => InboundRequestStatusEnum::CONTACTED->value,
    ]);

    $response->assertOk()
        ->assertJsonPath('data', null)
        ->assertJsonMissingPath('data.phone')
        ->assertJsonMissingPath('data.notes');
});

it('forbids staff without request access from listing requests', function (): void {
    $this->unauthorized_user();

    $this->getJson(route('api.v1.admin.organization-training-requests.index'))
        ->assertForbidden();
});

it('forbids staff without request access from reading or changing a request', function (): void {
    $this->unauthorized_user();
    $request = OrganizationTrainingRequest::factory()->create();

    $this->getJson(route('api.v1.admin.organization-training-requests.show', $request))
        ->assertForbidden();
    $this->patchJson(route('api.v1.admin.organization-training-requests.update-status', $request), [
        'status' => InboundRequestStatusEnum::CONTACTED->value,
    ])->assertForbidden();
    $this->patchJson(route('api.v1.admin.organization-training-requests.update-assignment', $request), [
        'staff_id' => null,
    ])->assertForbidden();
});

it('allows update-own staff to claim only their request', function (): void {
    $this->authorized_user([PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE_OWN]);
    $request = OrganizationTrainingRequest::factory()->create();
    $other   = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => Staff::factory()]);

    $this->patchJson(route('api.v1.admin.organization-training-requests.update-assignment', $request), [
        'staff_id' => $this->user->id,
    ])->assertOk();
    $this->patchJson(route('api.v1.admin.organization-training-requests.update-status', $request), [
        'status' => InboundRequestStatusEnum::RESOLVED->value,
    ])->assertOk();
    $this->patchJson(route('api.v1.admin.organization-training-requests.update-status', $other), [
        'status' => InboundRequestStatusEnum::RESOLVED->value,
    ])->assertForbidden();
});

it('retains the vendor snapshot and blocks vendor deletion', function (): void {
    $vendor = Vendor::factory()->create(['name' => 'Historical Department']);
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    $this->postJson(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => ['Leadership'],
    ])->assertCreated();
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => null]);
    $this->authorized_user([PermissionEnum::VENDOR_DELETE]);

    $response = $this->deleteJson(route('api.v1.admin.vendors.destroy', $vendor));

    $response->assertUnprocessable()->assertJsonFragment([
        'message' => __('messages.errors.model_has_relationship_data', [
            'related_model' => getModelLabel(OrganizationTrainingRequest::class),
        ]),
    ]);
    expect(OrganizationTrainingRequest::query()->firstOrFail()->vendor_snapshot)
        ->toBe(['id' => $vendor->id, 'name' => 'Historical Department']);
});

it('keeps the request vendor snapshot while future page courses follow a new vendor', function (): void {
    $originalVendor = Vendor::factory()->create(['name' => 'Original Department']);
    $newVendor      = Vendor::factory()->create(['name' => 'New Department']);
    $page           = OrganizationPage::query()->singleton()->firstOrFail();
    $page->update(['vendor_id' => $originalVendor->id]);

    $this->postJson(route('api.v1.shop.organization.training-requests.store'), [
        'first_name'             => 'Sara',
        'last_name'              => 'Ahmadi',
        'phone'                  => '09121234567',
        'position'               => 'HR manager',
        'organization_name'      => 'Example Organization',
        'requested_course_names' => ['Leadership'],
    ])->assertCreated();

    $newCourse = Course::factory()->create([
        'full_name' => 'New department course',
        'status'    => PublicationStatusEnum::PUBLISHED,
    ]);
    $newProduct = Product::factory()->withCourse($newCourse)->create([
        'vendor_id' => $newVendor->id,
    ]);
    ProductDeliveryOption::factory()->create(['product_id' => $newProduct->id]);
    $page->update(['vendor_id' => $newVendor->id]);
    app(CacheStore::class)->forget(CacheKey::OrganizationPage, ['limit' => 6]);

    $pageResponse = $this->getJson(route('api.v1.shop.organization.show'));

    $pageResponse->assertOk()
        ->assertJsonPath('data.vendor.name', 'New Department')
        ->assertJsonPath('data.recent_courses.0.name', 'New department course');
    expect(OrganizationTrainingRequest::query()->firstOrFail()->vendor_snapshot)
        ->toBe(['id' => $originalVendor->id, 'name' => 'Original Department']);
});
