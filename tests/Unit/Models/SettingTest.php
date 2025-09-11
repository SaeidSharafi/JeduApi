<?php

use App\Data\Admin\MediaData;

it('to array', function () {
    $setting = new \App\Models\Setting([
        'key'   => 'site_name',
        'value' => ['en' => 'My Site', 'fa' => 'سایت من'],
        'type'  => 'json',
        'group' => 'general',
    ]);

    $array = $setting->toArray();
    expect($array)
        ->and($array)->toBeArray()
        ->and($array)
        ->toHaveKeys([
            'key',
            'value',
            'type',
            'group',
        ])
        ->and($array['key'])->toBe('site_name')
        ->and($array['value'])->toBe(['en' => 'My Site', 'fa' => 'سایت من'])
        ->and($array['type'])->toBe('json')
        ->and($array['group'])->toBe('general');
});

it('get and set setting', function () {
    // Set a setting
    $setting = \App\Models\Setting::set('site_name', ['en' => 'My Site', 'fa' => 'سایت من'], 'json', 'general');

    expect($setting)->toBeInstanceOf(\App\Models\Setting::class)
        ->and($setting->key)->toBe('site_name')
        ->and($setting->value)->toBe(['en' => 'My Site', 'fa' => 'سایت من'])
        ->and($setting->type)->toBe('json')
        ->and($setting->group)->toBe('general');

    // Get the setting
    $value = \App\Models\Setting::get('site_name');
    expect($value)->toBe(['en' => 'My Site', 'fa' => 'سایت من']);

    // Get a non-existing setting with default value
    $defaultValue = \App\Models\Setting::get('non_existing_key', 'default_value');
    expect($defaultValue)->toBe('default_value');
});

it('get setting with images', function () {
    // Create a media item

    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $image1 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $image2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image2.jpg'))
        ->toDisk('public')
        ->upload();

    $media = \App\Models\Setting::set('site_logo', [
        'images' => [$image1->id, $image2->id]
    ], 'json', 'general');
    // Get the setting
    $value = \App\Models\Setting::get('site_logo');
    expect($value)->toBeArray()
        ->and($value['images'])->toBeArray()
        ->and(count($value['images']))->toBe(2)
        ->and($value['images'][0])->toBeInstanceOf(MediaData::class)
        ->and($value['images'][0]->id)->toBe($image1->id)
        ->and($value['images'][0]->url)->toBe($image1->url)
        ->and($value['images'][1])->toBeInstanceOf(MediaData::class)
        ->and($value['images'][1]->id)->toBe($image2->id)
        ->and($value['images'][1]->url)->toBe($image2->url);
});


it('get setting with non-array value', function () {
    // Set a setting with a non-array value
    $setting = \App\Models\Setting::set('site_name', 'My Site', 'text', 'general');

    expect($setting)->toBeInstanceOf(\App\Models\Setting::class)
        ->and($setting->key)->toBe('site_name')
        ->and($setting->value)->toBe('My Site')
        ->and($setting->type)->toBe('text')
        ->and($setting->group)->toBe('general');

    // Get the setting
    $value = \App\Models\Setting::get('site_name');
    expect($value)->toBe('My Site');
});


it('get setting with empty array value', function () {
    // Set a setting with an empty array value
    $setting = \App\Models\Setting::set('empty_setting', [], 'json', 'general');

    expect($setting)->toBeInstanceOf(\App\Models\Setting::class)
        ->and($setting->key)->toBe('empty_setting')
        ->and($setting->value)->toBe([])
        ->and($setting->type)->toBe('json')
        ->and($setting->group)->toBe('general');

    // Get the setting
    $value = \App\Models\Setting::get('empty_setting', 'default_value');
    expect($value)->toBe([]);
});
