<?php

declare(strict_types=1);

test('to array', function (): void {
    $admin = App\Models\Admin::factory()->create()->fresh();
    expect($admin->toArray())
        ->toEqual([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'is_admin' => $admin->is_admin,
            'created_at' => $admin->created_at->toISOString(),
            'updated_at' => $admin->updated_at->toISOString(),
        ]);
});
