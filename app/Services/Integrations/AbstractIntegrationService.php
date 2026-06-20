<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Services\SettingsService;
use Illuminate\Http\Client\Response;

abstract class AbstractIntegrationService
{
    protected array $config = [];

    public function __construct(protected readonly SettingsService $settings)
    {
        $this->resolveConfig();
    }

    abstract protected function getSettingKey(): SettingKeyEnum;

    abstract protected function getConfigFallbackPath(): string;

    abstract protected function validateConfig(): bool;

    final public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * Only throws when the *configuration* is broken — not when the integration is merely disabled.
     * Jobs should check isEnabled() themselves and return silently if false.
     */
    final public function assertConfigured(): void
    {
        if (! $this->validateConfig()) {
            throw new UnrecoverableProvisioningException(
                class_basename($this).' configuration is missing or invalid.'
            );
        }
    }

    /**
     * Convenience for non-provisioning consumers (e.g. SyncMoodleProgressJob).
     */
    final public function isReady(): bool
    {
        return $this->isEnabled() && $this->validateConfig();
    }

    protected function resolveConfig(): void
    {
        $fallback = config($this->getConfigFallbackPath(), []);
        $stored   = $this->settings->get($this->getSettingKey(), $fallback);
        // Preserve the fallback when stored value is null/false (not just empty array)
        $this->config = is_array($stored) ? $stored : (is_array($fallback) ? $fallback : []);
    }

    /**
     * Standardised HTTP error handler for JSON REST APIs (IMS, SpotPlayer).
     * Do NOT call this for BBB (XML) or Moodle (form/JSON hybrid) — handle those inline.
     */
    protected function handleHttpErrors(Response $response, string $endpoint): void
    {
        if ($response->successful()) {
            return;
        }

        $status   = $response->status();
        $metaData = [
            'http_status'       => $status,
            'endpoint'          => $endpoint,
            'raw_body_snippet'  => $this->sanitizeBody($response->body()),
            'validation_errors' => [],
        ];

        if ($status === 422) {
            $rawErrors                     = (array) data_get($response->json(), 'errors', []);
            $metaData['validation_errors'] = $rawErrors;
            $message                       = $this->formatValidationErrors($rawErrors)
                ?: "Validation failed on {$endpoint}.";

            throw new UnrecoverableProvisioningException($message, $status, null, $metaData);
        }

        $message = "HTTP {$status} on {$endpoint}.";

        if ($status >= 400 && $status < 500) {
            throw new UnrecoverableProvisioningException($message, $status, null, $metaData);
        }

        throw new RecoverableProvisioningException($message, $status, null, $metaData);
    }

    protected function sanitizeBody(string $body): string
    {
        $sanitized = preg_replace(
            ['/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/', '/\b09\d{9}\b/', '/\b\d{10}\b/'],
            '[REDACTED]',
            $body
        );

        return mb_substr($sanitized ?? $body, 0, 500);
    }

    /**
     * Formats 422 validation errors into a human-readable string for log messages.
     * Preserves the same detail level as the original ImsService::buildException().
     */
    private function formatValidationErrors(array $rawErrors): string
    {
        $flat = [];
        foreach ($rawErrors as $field => $messages) {
            foreach ((array) $messages as $msg) {
                $flat[] = "{$field}: ".$this->sanitizeBody((string) $msg);
            }
        }

        return implode('; ', $flat);
    }
}
