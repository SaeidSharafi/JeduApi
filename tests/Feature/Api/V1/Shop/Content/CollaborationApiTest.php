<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

describe('CollaborationPageController', function () {

    it('should return collaboration page configuration', function () {
        $response = $this->getJson(route('api.v1.shop.collaboration.show'));
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'title',
                'content',
                'image',
            ],
            'metadata',
        ]);
    });
});

describe('CollaborationRequestController', function () {

    it('should submit collaboration request', function () {
        Storage::fake('local');
        $document = UploadedFile::fake()->create('attachment.pdf', 100, 'application/pdf');
        $postData = [
            'full_name'  => 'John Doe',
            'email'      => 'email@example.com',
            'phone'      => '09123456789',
            'message'    => 'I would like to collaborate.',
            'attachment' => $document,
        ];
        $response = $this->postJson(route('api.v1.shop.collaboration.store'), $postData);
        $response->assertCreated();
        $response->assertJson([
            'message'  => __('shop.responses.forms.collaboration_request_submitted'),
            'data'     => null,
            'metadata' => [],
        ]);

        Storage::disk('local')->assertExists('forms/attachments/'.$document->name);
        $this->assertDatabaseHas('collaboration_requests', [
            'full_name' => $postData['full_name'],
            'email'     => $postData['email'],
            'phone'     => $postData['phone'],
            'message'   => $postData['message'],
        ]);
        $this->assertDatabaseHas('media', [
            'filename' => 'attachment',
            'disk'     => 'local',
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_type' => App\Enums\System\MorphTypeEnum::COLLABORATION_REQUEST->value,
            'mediable_id'   => App\Models\CollaborationRequest::first()->id,
        ]);

    });
});
