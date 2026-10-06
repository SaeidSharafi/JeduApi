<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Staff;
use App\Policies\Admin\BundlePolicy;
use Spatie\Permission\Models\Role;

it('models bundles as composite delivery options', function (): void {
    expect(FulfillmentTypeEnum::COMPOSITE->getDeliveryMethods())
        ->toBe([DeliveryMethodEnum::BUNDLE]);
    expect(DeliveryMethodEnum::BUNDLE->getFulfillmentType())
        ->toBe(FulfillmentTypeEnum::COMPOSITE);
});

it('rejects virtuality checks for structural bundle delivery options', function (): void {
    expect(fn (): bool => DeliveryMethodEnum::BUNDLE->isVirtual())
        ->toThrow(LogicException::class);
});

it('registers bundle as a productable model', function (): void {
    expect(ProductableEnum::BUNDLE->getModelClass())->toBe(Bundle::class)
        ->and(ProductableEnum::BUNDLE->value)->toBe('bundle');
});

describe('Bundle permission boundaries', function (): void {
    it('allows bundle listing only with the view-any permission', function (PermissionEnum $permission, bool $allowed): void {
        $staff = Staff::factory()->create();
        $role  = Role::create([
            'name'       => 'bundle_listing_'.$permission->name,
            'label'      => 'Bundle listing '.$permission->name,
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo($permission->value);
        $staff->assignRole($role);

        expect((new BundlePolicy())->viewAny($staff->fresh()))->toBe($allowed);
    })->with([
        'view-any permission'           => [PermissionEnum::BUNDLE_VIEW_ANY, true],
        'single bundle view permission' => [PermissionEnum::BUNDLE_VIEW, false],
    ]);

    it('allows deleting a bundle only with the delete permission', function (PermissionEnum $permission, bool $allowed): void {
        $staff = Staff::factory()->create();
        $role  = Role::create([
            'name'       => 'bundle_deletion_'.$permission->name,
            'label'      => 'Bundle deletion '.$permission->name,
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo($permission->value);
        $staff->assignRole($role);
        $bundle = Bundle::factory()->create();

        expect((new BundlePolicy())->delete($staff->fresh(), $bundle))->toBe($allowed);
    })->with([
        'delete permission' => [PermissionEnum::BUNDLE_DELETE, true],
        'update permission' => [PermissionEnum::BUNDLE_UPDATE, false],
    ]);
});
