<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Staff;
use App\Rules\IranMobilePhoneRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class CreateFirstAdminStaffCommand extends Command
{
    protected $signature = 'staff:create-first-admin';

    protected $description = 'Interactively create the first super-admin staff account';

    public function handle(): int
    {
        if (Staff::query()->where('is_admin', true)->exists()) {
            $this->info('A super-admin staff account already exists; no new account was created.');

            return self::SUCCESS;
        }

        $staffData = [
            'name'                  => $this->ask('Name'),
            'email'                 => $this->ask('Email'),
            'phone'                 => $this->ask('Iranian phone number'),
            'password'              => $this->secret('Password (minimum 8 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($staffData, [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:staff,email'],
            'phone'    => ['required', new IranMobilePhoneRule(), 'unique:staff,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($staffData): void {
            $staff = Staff::create([
                'name'     => $staffData['name'],
                'email'    => $staffData['email'],
                'phone'    => $staffData['phone'],
                'password' => Hash::make($staffData['password']),
            ]);

            $staff->forceFill(['is_admin' => true])->save();
        });

        $this->info(sprintf('Created the first super-admin staff account for %s.', $staffData['email']));

        return self::SUCCESS;
    }
}
