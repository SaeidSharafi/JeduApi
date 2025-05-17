<?php

test('to array', function (): void {
    $admin = \App\Models\Admin::factory()->create()->fresh();
    expect($admin->toArray())
        ->toEqual([
            'id'         => $admin->id,
            'name'       => $admin->name,
            'email'      => $admin->email,
            'phone'      => $admin->phone,
            'created_at' => $admin->created_at->toISOString(),
            'updated_at' => $admin->updated_at->toISOString(),
        ]);
});
