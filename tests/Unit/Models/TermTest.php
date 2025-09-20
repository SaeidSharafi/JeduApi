<?php

declare(strict_types=1);

test('to array', function (): void {
    $term = App\Models\Term::factory()->create()->fresh();

    $array = $term->toArray();
    expect($array)->toBeArray()
        ->and($array)->toHaveKeys([
            'id',
            'name',
            'status',
            'academic_year',
            'start_date',
            'end_date',
            'created_at',
            'updated_at',
        ]);
});
