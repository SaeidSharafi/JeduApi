<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;

trait AuthTestTrait
{
    protected Authenticatable $user;

    public function unauthorized_user($guard = 'staff'): self
    {
        if (! isset($this->user)) {
            $this->user = Staff::factory()->create();
        }

        return $this->actingAs($this->user->fresh(), $guard);
    }

    public function authorized_user(
        array $permission = [],
        $guard = 'staff'
    ): self {
        if (! isset($this->user)) {
            $this->user = Staff::factory()->create();
        }
        $role = Role::updateOrCreate([
            'name'       => 'manager_test',
            'label'      => 'ManagerTest',
            'guard_name' => $guard,
        ]);

        $role->syncPermissions($permission);

        $this->user->assignRole('manager_test');

        return $this->actingAs($this->user->fresh(), $guard);
    }

    public function customer(?User $user = null): self
    {
        $this->user = $user ?: User::factory()->create();

        return $this->actingAs($this->user->fresh(), 'user');
    }

    public function admin_user(?Staff $staff = null ): self
    {
        $this->user = $staff ?: Staff::forceCreate(
            Staff::factory()->make([
                'phone'    => '09300000000',
                'email'    => 'staff@example.com',
                'is_admin' => true,
            ])->toArray()
        );

        return $this->actingAs($this->user->fresh(), 'staff');
    }
}
