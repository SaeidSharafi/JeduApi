<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

final class EncryptSettingSecretsCommand extends Command
{
    protected $signature = 'settings:encrypt-secrets
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'One-time migration: encrypt plaintext secret sub-fields in integration settings. Idempotent — already-encrypted values are skipped.';

    public function __construct(private readonly SettingsService $settingsService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $integrationKeys = [
            SettingKeyEnum::IMS,
            SettingKeyEnum::MOODLE,
            SettingKeyEnum::BIG_BLUE_BUTTON,
            SettingKeyEnum::SPOT_PLAYER,
        ];

        $totalEncrypted = 0;
        $totalSkipped   = 0;

        foreach ($integrationKeys as $key) {
            $setting = Setting::where('key', $key->value)->first();

            if (! $setting) {
                $this->line("  <fg=gray>SKIP</> {$key->value} — no record in DB.");

                continue;
            }

            $secretFields = $key->secretFields();

            if ($secretFields === []) {
                continue;
            }

            $value   = $setting->value;
            $changed = false;

            if (! is_array($value)) {
                $this->line("  <fg=gray>SKIP</> {$key->value} — value is not an array.");

                continue;
            }

            foreach ($secretFields as $field) {
                if (! isset($value[$field]) || ! is_string($value[$field]) || $value[$field] === '') {
                    $totalSkipped++;

                    continue;
                }

                if ($this->isAlreadyEncrypted($value[$field])) {
                    $this->line("  <fg=gray>SKIP</> {$key->value}.{$field} — already encrypted.");
                    $totalSkipped++;

                    continue;
                }

                $this->line("  <fg=green>ENCRYPT</> {$key->value}.{$field}");
                $value[$field] = Crypt::encryptString($value[$field]);
                $changed       = true;
                $totalEncrypted++;
            }

            if ($changed && ! $isDryRun) {
                $setting->update(['value' => $value]);
                $this->settingsService->forget();
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info("Dry run complete. Would encrypt {$totalEncrypted} field(s), skip {$totalSkipped}.");
        } else {
            $this->info("Done. Encrypted {$totalEncrypted} field(s), skipped {$totalSkipped} (already encrypted or empty).");
        }

        return self::SUCCESS;
    }

    /**
     * Detects whether a string is already a Laravel-encrypted payload.
     * Laravel's Crypt::encryptString produces a base64-encoded JSON with
     * keys: iv, value, mac (and optionally tag). Attempting to decrypt is
     * the most reliable check.
     */
    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
