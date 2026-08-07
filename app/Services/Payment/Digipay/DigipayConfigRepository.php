<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Gateway\DigipayException;
use App\Services\SettingsService;

final class DigipayConfigRepository
{
    /** @var array<string, mixed> */
    private array $settings;

    public function __construct(SettingsService $settingsService)
    {
        $this->settings = $settingsService->get(SettingKeyEnum::DIGIPAY, []);
    }

    public function getClientId(): string
    {
        return $this->required('client_id');
    }

    public function getClientSecret(): string
    {
        return $this->required('client_secret');
    }

    public function getUsername(): string
    {
        return $this->required('username');
    }

    public function getPassword(): string
    {
        return $this->required('password');
    }

    public function isSandbox(): bool
    {
        return (bool) ($this->settings['sandbox_mode'] ?? false);
    }

    public function getBaseUrl(): string
    {
        $env = $this->isSandbox() ? 'sandbox' : 'production';

        return config("payments.digipay.endpoints.{$env}.base_url");
    }

    public function getTimeout(): int
    {
        return (int) config('payments.digipay.timeout', 30);
    }

    private function required(string $key): string
    {
        $value = $this->settings[$key] ?? '';

        if (empty($value)) {
            throw new DigipayException(__('payment_gateways.digipay.errors.config_missing', ['key' => $key]));
        }

        return $value;
    }
}
