<?php

declare(strict_types=1);

test('to array', function () {
    $teahcer = App\Models\Teacher::factory()->create()->fresh();

    $array = $teahcer->toArray();
    expect($array)->toBeArray()
        ->and($array)->toHaveKeys([
            'id',
            'first_name',
            'last_name',
            'bio',
            'rate',
            'email',
            'phone',
            'gender',
            'birth_date',
            'social_links',
            'user_id',
            'created_at',
            'updated_at',
        ]);
});
