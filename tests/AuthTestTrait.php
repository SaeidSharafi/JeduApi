<?php

namespace Tests;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait AuthTestTrait
{
    protected Authenticatable $user;
    public function unauthorized_user($guard = 'admin'): self
    {
        if ( ! isset($this->user)) {
            $this->user = Admin::factory()->create();
        }
        return $this->actingAs($this->user->fresh(),$guard);
    }

    public function authorized_user(
        array $permission,
        $guard = 'admin'
    ): self {
        if ( ! isset($this->user)) {
            $this->user = Admin::factory()->create();
        }
        $role = Role::updateOrCreate([
            'name'          => 'manager_test',
            'label'         => 'ManagerTest',
            'guard_name'    => $guard,
        ]);

        $role->syncPermissions($permission);

        $this->user->assignRole('manager_test');

        return $this->actingAs($this->user->fresh(), $guard);
    }

    public function student(): self
    {
        $this->user = User::factory()->create();

        return $this->actingAs($this->user->fresh(),'user');
    }

    public function admin_user($guard = 'admin'): self
    {
        $this->user = Admin::forceCreate(
            Admin::factory()->make([
                'phone' => '09300000000',
                'email' => 'admin@example.com',
                'is_admin' => true])->toArray()
        );

        return $this->actingAs($this->user->fresh(), $guard);
    }
}
