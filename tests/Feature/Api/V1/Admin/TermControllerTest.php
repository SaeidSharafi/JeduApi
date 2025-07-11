<?php

declare(strict_types=1);

use App\Enums\TermStatusEnum;
use App\Models\Term;

uses(Tests\AuthTestTrait::class);

describe('TermController List Filters', function (): void {
    it('should filter by name', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW_ANY]);
        Term::factory(20)->create();
        Term::factory()->create(['name' => 'XFall 2024']);
        $response = $this->getJson(route('api.v1.admin.term.index', ['filter' => ['name' => 'XFall 2024']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['name' => 'XFall 2024']);
    });

    it('should filter by status', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW_ANY]);
        Term::factory(20)->create(
            ['status' => TermStatusEnum::INACTIVE->value]
        );

        Term::factory()->create(['status' => TermStatusEnum::ACTIVE->value]);
        $response = $this->getJson(route('api.v1.admin.term.index', ['filter' => ['status' => TermStatusEnum::ACTIVE->value]]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['status' => [
            'label' => __('enums.TermStatusEnum.active'),
            'value' => TermStatusEnum::ACTIVE->value,
        ]]);
    });

    it('should filter by academic_year', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW_ANY]);
        Term::factory(20)->create();
        Term::factory()->create(['academic_year' => 'X2024-2025']);
        $response = $this->getJson(route('api.v1.admin.term.index', ['filter' => ['academic_year' => 'X2024-2025']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['academic_year' => 'X2024-2025']);
    });

    it('should filter by name and status', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW_ANY]);
        Term::factory(20)->create();
        Term::factory()->create(['name' => 'XSpring 2025', 'status' => 'planning']);
        $response = $this->getJson(route('api.v1.admin.term.index', ['filter' => ['name' => 'XSpring 2025', 'status' => 'planning']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['name' => 'XSpring 2025', 'status' => [
            'label' => __('enums.TermStatusEnum.planning'),
            'value' => TermStatusEnum::PLANNING->value,
        ]]);
    });
});

describe('TermController Test', function () {
    it('should list terms', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW_ANY]);
        Term::factory(20)->create();
        $response = $this->getJson(route('api.v1.admin.term.index'));
        $response->assertOk();
        $response->assertJsonCount(15, 'data.data');
    });

    it('should create a term', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_CREATE]);
        $data     = Term::factory()->make();
        $response = $this->postJson(route('api.v1.admin.term.store'), $data->toArray());
        $response->assertCreated();
        $this->assertDatabaseHas('terms', ['name' => $data->name]);
    });

    it('should show a term', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW]);
        $term     = Term::factory()->create();
        $response = $this->getJson(route('api.v1.admin.term.show', ['term' => $term]));
        $response->assertOk();
        $response->assertJsonFragment(['name' => $term->name]);
    });

    it('should update a term', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_UPDATE]);
        $term     = Term::factory()->create();
        $data     = Term::factory()->make();
        $response = $this->putJson(route('api.v1.admin.term.update', ['term' => $term]), $data->toArray());
        $response->assertOk();
        $this->assertDatabaseHas('terms', ['id' => $term->id, 'name' => $data->name]);
    });

    it('should delete a term', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_DELETE]);
        $term     = Term::factory()->create();
        $response = $this->deleteJson(route('api.v1.admin.term.destroy', ['term' => $term]));
        $response->assertNoContent();
        $this->assertDatabaseMissing('terms', ['id' => $term->id]);
    });
    it('should not delete a term with related data', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_DELETE]);
        $term = Term::factory()->create();
        App\Models\Product::factory()->create(['term_id' => $term->id]);
        $response = $this->deleteJson(route('api.v1.admin.term.destroy', ['term' => $term]));
        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('messages.errors.model_has_relationship_data',
                    ['related_model' => getModelLabel(App\Models\Product::class)]),
            ]);
        $this->assertDatabaseHas('terms', ['id' => $term->id]);
    });
    it('should not create a term with missing required fields', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_CREATE]);
        $response = $this->postJson(route('api.v1.admin.term.store'), []);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    });

    it('should not create a term with invalid dates', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_CREATE]);
        $data               = Term::factory()->make()->toArray();
        $data['start_date'] = 'not-a-date';
        $data['end_date']   = 'not-a-date';
        $response           = $this->postJson(route('api.v1.admin.term.store'), $data);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_date', 'end_date']);
    });

    it('should not update a term with invalid status', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_UPDATE]);
        $term           = Term::factory()->create();
        $data           = Term::factory()->make()->toArray();
        $data['status'] = 'invalid-status';
        $response       = $this->putJson(route('api.v1.admin.term.update', ['term' => $term]), $data);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    });

    it('should return 404 for non-existent term', function () {
        $this->authorized_user([App\Enums\PermissionEnum::TERM_VIEW]);
        $response = $this->getJson(route('api.v1.admin.term.show', ['term' => 999999]));
        $response->assertNotFound();
    });

    it('should not allow unauthorized user to create term', function () {
        $this->unauthorized_user();
        $data     = Term::factory()->make()->toArray();
        $response = $this->postJson(route('api.v1.admin.term.store'), $data);
        $response->assertForbidden();
    });

    it('should not allow unauthorized user to update term', function () {
        $this->unauthorized_user();
        $term     = Term::factory()->create();
        $data     = Term::factory()->make()->toArray();
        $response = $this->putJson(route('api.v1.admin.term.update', ['term' => $term]), $data);
        $response->assertForbidden();
    });

    it('should not allow unauthorized user to delete term', function () {
        $this->unauthorized_user();
        $term     = Term::factory()->create();
        $response = $this->deleteJson(route('api.v1.admin.term.destroy', ['term' => $term]));
        $response->assertForbidden();
    });
});
