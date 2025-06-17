<?php

declare(strict_types=1);

it('to Array', function () {
    $vendor = App\Models\Vendor::factory()->create()->fresh();

    expect($vendor->toArray())->toEqual([
        'id'            => $vendor->id,
        'name'          => $vendor->name,
        'email'         => $vendor->email,
        'phone'         => $vendor->phone,
        'phone2'        => $vendor->phone2,
        'address'       => $vendor->address,
        'map_location'  => $vendor->map_location,
        'logo_url'      => $vendor->logo_url,
        'favicon_url'   => $vendor->favicon_url,
        'social_links'  => $vendor->social_links,
        'theme_options' => $vendor->theme_options,
        'created_at'    => $vendor->created_at->format('Y-m-d H:i:s'),
        'updated_at'    => $vendor->updated_at->format('Y-m-d H:i:s'),
    ]);
})->group('vendor');
