<?php

declare(strict_types=1);

use App\Data\Admin\Category\CategorizableListItemData;
use App\Enums\System\MorphTypeEnum;

describe('CategorizableListItemData', function (): void {
    it('can be created from a Categorizable model', function (): void {
        // Mock a Categorizable model
        $category = App\Models\Category::factory()->create();
        $course   = App\Models\Course::factory()->create(['short_name' => 'Sample Course']);
        $category->courses()->attach($course, ['good_for_start' => true]);

        $categorizablePivot = App\Models\Categorizable::where('category_id', $category->id)
            ->where('categorizable_id', $course->id)
            ->where('categorizable_type', MorphTypeEnum::COURSE->value)
            ->with('categorizable')
            ->first();

        $data = CategorizableListItemData::fromModel($categorizablePivot);

        expect($data->pivot_id)->toBe($categorizablePivot->id);
        expect($data->category_id)->toBe($category->id);
        expect($data->categorizable_id)->toBe($course->id);
        expect($data->categorizable_type)->toBe(MorphTypeEnum::COURSE->translate());
        expect($data->categorizable_name)->toBe('Sample Course');
        expect($data->good_for_start)->toBeTrue();
    });

    it('get categorizable name fallback works correctly', function (): void {
        $fakeModel = new class extends Illuminate\Database\Eloquent\Model
        {
            public $name = 'Test Name';
        };
        $reflection = new ReflectionClass(CategorizableListItemData::class);
        $method     = $reflection->getMethod('getCategorizableName');
        $method->setAccessible(true);
        $name = $method->invoke(null, $fakeModel);
        expect($name)->toBe('Test Name');

        $fakeModel2 = new class extends Illuminate\Database\Eloquent\Model
        {
            public $title = 'Test Title';
        };
        $name2 = $method->invoke(null, $fakeModel2);
        expect($name2)->toBe('Test Title');

        $fakeModel3 = new class extends Illuminate\Database\Eloquent\Model
        {
            public $short_name = 'Test Short Name';
        };
        $name3 = $method->invoke(null, $fakeModel3);
        expect($name3)->toBe('Test Short Name');

        $fakeModel4 = new class extends Illuminate\Database\Eloquent\Model
        {
            public $full_name = 'Test Full Name';
        };
        $name4 = $method->invoke(null, $fakeModel4);
        expect($name4)->toBe('Test Full Name');
    });
});
