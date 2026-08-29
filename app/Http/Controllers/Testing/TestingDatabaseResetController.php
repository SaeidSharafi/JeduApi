<?php

declare(strict_types=1);

namespace App\Http\Controllers\Testing;

use App\Contracts\ApiResponseInterface;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use SaeidSharafi\LaravelPermissionGenerator\Commands\PermissionsSync;
use Throwable;

final class TestingDatabaseResetController extends Controller
{
    public function reset(Request $request): ApiResponseInterface
    {
        abort_unless(
            app()->environment('e2e')
            && (string) config('e2e.control_key') !== ''
            && hash_equals((string) config('e2e.control_key'), (string) $request->header('X-E2E-Key')),
            403,
            'Unauthorized environment.',
        );

        Artisan::call('migrate:fresh', ['--force' => true]);

        // Package registers permissions:sync only when runningInConsole();
        // FrankenPHP requests are not, so register it manually.
        app(ConsoleKernel::class)->registerCommand(app(PermissionsSync::class));

        Artisan::call('permissions:sync', ['--guard' => 'staff']);
        Artisan::call('permissions:sync', ['--guard' => 'user']);

        try {
            Redis::connection('default')->flushdb();
            Artisan::call('horizon:purge');
        } catch (Throwable $e) {
            // Ignore if Redis is not running in pure unit-test mock mode
        }

        // 1. Seed Default Staff User
        $staff = Staff::create([
            'name'     => 'Test Admin',
            'email'    => 'admin@jedu.test',
            'phone'    => '09121111111',
            'password' => 'password123',
        ]);
        $staff->is_admin = true;
        $staff->save();
        $staffToken = $staff->createToken('test-suite')->plainTextToken;

        // 2. Seed Default Customer User
        $customer = User::create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'customer@jedu.test',
            'phone'      => '09120000000',
            'password'   => 'password123',
            'civil_id'   => '0011223344',
        ]);
        $customerToken = $customer->createToken('test-suite')->plainTextToken;

        return apiResponse()->success([
            'staff' => [
                'id'    => $staff->id,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'token' => $staffToken,
            ],
            'customer' => [
                'id'    => $customer->id,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'token' => $customerToken,
            ],
        ], 'Database and Horizon queues reset successfully');
    }
}
