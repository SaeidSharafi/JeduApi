<?php

declare(strict_types=1);

namespace App\Actions\Testing;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use SaeidSharafi\LaravelPermissionGenerator\Commands\PermissionsSync;
use Throwable;

final class ResetE2eEnvironmentAction
{
    private const LOCK_KEY = 'e2e:database-reset';

    private const PASSWORD = 'password123';

    /**
     * @return array{reset_id: string, readiness: string, staff: array<string, mixed>, customer: array<string, mixed>}|null
     */
    public function handle(): ?array
    {
        $lock = Cache::lock(self::LOCK_KEY, 300);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->reset();
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{reset_id: string, readiness: string, staff: array<string, mixed>, customer: array<string, mixed>}
     */
    private function reset(): array
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        // Package registers permissions:sync only when runningInConsole();
        // FrankenPHP requests are not, so register it manually.
        app(ConsoleKernel::class)->registerCommand(app(PermissionsSync::class));

        Artisan::call('permissions:sync', ['--guard' => 'staff']);
        Artisan::call('permissions:sync', ['--guard' => 'user']);

        try {
            Redis::connection('default')->flushdb();
            Artisan::call('horizon:purge');
        } catch (Throwable) {
            // Redis may be unavailable in a minimal E2E bootstrap.
        }

        $staff = Staff::factory()->isSuperAdmin()->create([
            'name'     => 'E2E Admin',
            'email'    => $this->credential('admin'),
            'phone'    => '0912'.random_int(1000000, 9999999),
            'password' => Hash::make(self::PASSWORD),
        ]);
        $customer = User::factory()->withPassword()->create([
            'email' => $this->credential('customer'),
            'phone' => '0911'.random_int(1000000, 9999999),
        ]);

        return [
            'reset_id'  => (string) Str::uuid7(),
            'readiness' => 'ready',
            'staff'     => [
                'id'       => $staff->id,
                'email'    => $staff->email,
                'phone'    => $staff->phone,
                'password' => self::PASSWORD,
                'token'    => $staff->createToken('e2e-reset')->plainTextToken,
            ],
            'customer' => [
                'id'       => $customer->id,
                'email'    => $customer->email,
                'phone'    => $customer->phone,
                'password' => self::PASSWORD,
                'token'    => $customer->createToken('e2e-reset')->plainTextToken,
            ],
        ];
    }

    private function credential(string $prefix): string
    {
        return $prefix.'+'.Str::lower(Str::random(12)).'@jedu.test';
    }
}
