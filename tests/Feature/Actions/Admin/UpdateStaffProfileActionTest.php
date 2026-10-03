<?php

declare(strict_types=1);

use App\Actions\Admin\UpdateStaffProfileAction;
use App\Data\Admin\UpdateStaffProfileData;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;

function updateStaffData(array $overrides = []): UpdateStaffProfileData
{
    return new UpdateStaffProfileData(
        name: $overrides['name']         ?? 'Jane Doe',
        email: $overrides['email']       ?? 'jane@example.com',
        phone: $overrides['phone']       ?? '09123456789',
        password: $overrides['password'] ?? null,
    );
}

describe('UpdateStaffProfileAction', function (): void {
    beforeEach(function (): void {
        $this->action = app(UpdateStaffProfileAction::class);
    });

    it('updates name, email, and phone on the staff record', function (): void {
        $staff = Staff::factory()->create();

        $this->action->handle(updateStaffData(), $staff);

        $fresh = $staff->fresh();
        expect($fresh->name)->toBe('Jane Doe');
        expect($fresh->email)->toBe('jane@example.com');
        expect($fresh->phone)->toBe('09123456789');

        $this->assertDatabaseHas('staff', [
            'id'    => $staff->id,
            'name'  => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '09123456789',
        ]);
    });

    it('re-hashes the password when one is provided in the DTO', function (): void {
        $staff  = Staff::factory()->create(['password' => Hash::make('original-secret')]);
        $before = $staff->fresh()->password;

        $this->action->handle(updateStaffData(['password' => 'new-secret-123']), $staff);

        $after = $staff->fresh()->password;
        expect($after)->not->toBe($before);
        expect(Hash::check('new-secret-123', $after))->toBeTrue();
    });

    it('leaves the password hash untouched when the DTO password is null', function (): void {
        $staff  = Staff::factory()->create(['password' => Hash::make('original-secret')]);
        $before = $staff->fresh()->password;

        $this->action->handle(updateStaffData(), $staff);

        expect($staff->fresh()->password)->toBe($before);
        expect(Hash::check('original-secret', $staff->fresh()->password))->toBeTrue();
    });

    it('persists the updates through the transaction', function (): void {
        $staff = Staff::factory()->create();

        $this->action->handle(updateStaffData(), $staff);

        expect(Staff::query()->whereKey($staff->id)->exists())->toBeTrue();
        expect(Staff::query()->whereKey($staff->id)->value('name'))->toBe('Jane Doe');
        expect(Staff::query()->whereKey($staff->id)->value('email'))->toBe('jane@example.com');
    });
});
