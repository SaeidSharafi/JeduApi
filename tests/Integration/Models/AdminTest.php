<?php

declare(strict_types=1);

test('to array', function (): void {
    $staff = App\Models\Staff::factory()->create()->fresh();
    expect($staff->toArray())
        ->toEqual([
            'id'         => $staff->id,
            'name'       => $staff->name,
            'email'      => $staff->email,
            'phone'      => $staff->phone,
            'is_admin'   => $staff->is_admin,
            'created_at' => $staff->created_at?->utc()->toJSON(),
            'updated_at' => $staff->updated_at?->utc()->toJSON(),
        ]);
});
