<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Prepares provisioning failure context for logging and for debug responses.
 *
 * Provisioning errors cross an external boundary, so their raw payloads carry
 * learner PII (emails, phone numbers, national ids) and customer entitlements
 * (SpotPlayer licence keys). Every path that writes that context — the dedicated
 * provisioning log channel and the `app.debug` response payload — goes through
 * here, so there is exactly one redaction implementation.
 */
final class ProvisioningErrorContext
{
    /**
     * Response-body keys whose values are secrets or entitlements and must never
     * be written anywhere, even truncated.
     *
     * @var list<string>
     */
    private const array SENSITIVE_KEYS = [
        'license_key',
        'player_url',
        'wstoken',
        'token',
        'api_token',
        'api_key',
        'password',
        'secret',
    ];

    /**
     * Metadata keys whose value is raw upstream text and therefore needs the body
     * scrubber rather than key-level redaction.
     *
     * @var list<string>
     */
    private const array BODY_KEYS = [
        'raw_body_snippet',
        'raw_response',
        'debuginfo',
    ];

    /**
     * Redact PII patterns from an upstream response body.
     */
    public static function sanitizeBody(string $body): string
    {
        $sanitized = preg_replace(
            ['/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/', '/\b09\d{9}\b/', '/\b\d{10}\b/'],
            '[REDACTED]',
            $body
        );

        return mb_substr($sanitized ?? $body, 0, 500);
    }

    /**
     * Redact a provisioning metadata array: known sensitive keys are replaced,
     * raw upstream bodies are scrubbed of PII, and arrays are walked recursively.
     *
     * @param  array<string, mixed>  $metaData
     * @return array<string, mixed>
     */
    public static function sanitize(array $metaData): array
    {
        $sanitized = [];

        foreach ($metaData as $key => $value) {
            if (in_array((string) $key, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (in_array((string) $key, self::BODY_KEYS, true)) {
                $serialized = is_string($value) ? $value : (json_encode($value) ?: '[unserializable]');

                $sanitized[$key] = self::sanitizeBody($serialized);

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $sanitized[$key] = self::sanitize($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * The first frame outside this helper and the exception classes, i.e. the throw
     * site that actually failed.
     *
     * The provisioning exceptions are abstract and their context always passes
     * through this class, so neither `__METHOD__` nor a single-frame backtrace can
     * name the caller. Frames are skipped by prefix instead of by index because the
     * throw sites are constructors (no extra frame) while `AbstractIntegrationService`
     * throws from a method (one extra frame).
     */
    public static function origin(): ?string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($frames as $frame) {
            $class = $frame['class'] ?? null;

            if (! is_string($class)) {
                continue;
            }

            if (str_starts_with($class, 'App\\Exceptions\\') || $class === self::class) {
                continue;
            }

            return $class.'::'.($frame['function'] ?? 'unknown');
        }

        return null;
    }
}
