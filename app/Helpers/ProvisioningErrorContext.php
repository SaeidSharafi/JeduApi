<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Prepares provisioning failure context for storage, logging and debug responses.
 *
 * Two policies live here, both deny-lists, because providers and upstream APIs own
 * their payload shapes and change them without notice:
 *
 * - `redactSecrets()` guards the database and the diagnostics API. It replaces
 *   secret-bearing keys and leaves every other key and value alone. Learner PII is
 *   deliberately kept: it already sits in the clear on `users` and is rendered to
 *   the same administrators on the enrollment page, so scrubbing it here protects
 *   nothing while corrupting real identifiers — the national-id rule also matches
 *   any 10-digit string, including course and order ids.
 * - `sanitize()` additionally scrubs that PII, and is used for everything written
 *   outside the database: the dedicated provisioning log channel and the
 *   `app.debug` response payload.
 */
final class ProvisioningErrorContext
{
    /**
     * Keys whose values are secrets or entitlements and must never be written
     * anywhere, even truncated. Matched by exact name.
     *
     * `key` is listed because SpotPlayer returns the licence in a bare `key`
     * field, which also arrives nested inside a decoded response body.
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
        'key',
    ];

    /**
     * Key-name fragments that mark a secret, so an unanticipated name such as
     * `private_token` or `X-Api-Key` is redacted without being enumerated.
     *
     * @var list<string>
     */
    private const array SENSITIVE_KEY_FRAGMENTS = [
        'token',
        'secret',
        'password',
        'passwd',
        'credential',
        'authorization',
        'api_key',
        'apikey',
        'api-key',
        'license',
        'licence',
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
     * Redact a provisioning metadata array: secret-bearing keys are replaced, and
     * raw upstream bodies and every other string value are scrubbed of PII.
     *
     * Used for anything written outside the database. Unrecognised keys are kept —
     * see {@see self::redactSecrets()} for why this is a deny-list.
     *
     * @param  array<array-key, mixed>  $metaData
     * @return array<array-key, mixed>
     */
    public static function sanitize(array $metaData): array
    {
        $sanitized = [];

        foreach ($metaData as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (in_array((string) $key, self::BODY_KEYS, true)) {
                // A decoded body can carry its own secret fields, so redact those
                // before it is serialised, then scrub the PII the text may hold.
                $body       = is_array($value) ? self::redactSecrets($value) : $value;
                $serialized = is_string($body) ? $body : (json_encode($body) ?: '[unserializable]');

                $sanitized[$key] = self::sanitizeBody($serialized);

                continue;
            }

            if (is_array($value)) {
                /** @var array<array-key, mixed> $value */
                $sanitized[$key] = self::sanitize($value);

                continue;
            }

            // Keys outside SENSITIVE_KEYS/BODY_KEYS still carry upstream free text
            // (Moodle's `message`, for instance), so every remaining string gets the
            // same PII scrub. Without this, logging those keys would bypass the
            // redaction the body keys already receive.
            $sanitized[$key] = is_string($value) ? self::sanitizeBody($value) : $value;
        }

        return $sanitized;
    }

    /**
     * Redact secret-bearing keys, keeping every other key and value as it is.
     *
     * This is the database and diagnostics-API policy. It is a deny-list on
     * purpose: providers and upstream APIs own these payload shapes and change
     * them without notice, so an unrecognised key is kept rather than dropped.
     * A key-name allow-list fails closed and silently, which is exactly what once
     * reduced every provisioning failure to a placeholder.
     *
     * Matching is by exact name plus fragment, so an unanticipated `private_token`
     * or `X-Api-Key` is still caught. No PII scrubbing happens here: learner PII is
     * already plaintext on `users` and is shown to the same administrators on the
     * enrollment page, while the PII rules would also redact unrelated 10-digit
     * identifiers and make the stored context harder to trust.
     *
     * @param  array<array-key, mixed>  $metaData
     * @return array<array-key, mixed>
     */
    public static function redactSecrets(array $metaData): array
    {
        $redacted = [];

        foreach ($metaData as $key => $value) {
            $redacted[$key] = self::isSensitiveKey((string) $key)
                ? '[REDACTED]'
                : (is_array($value) ? self::redactSecrets($value) : $value);
        }

        return $redacted;
    }

    /**
     * Flatten a provider validation-error payload into `field => message`.
     *
     * Providers answer either `field => message` or Laravel-style
     * `field => [messages]`. Diagnostics render one line per field, so array
     * values are joined; JSON-encoding them previously surfaced
     * `gender: ["The selected gender is invalid."]` to operators. Messages are
     * length-capped for storage but not PII-scrubbed — see
     * {@see self::redactSecrets()}.
     *
     * @param  array<array-key, mixed>  $validationErrors
     * @return array<string, string>
     */
    public static function flattenValidationErrors(array $validationErrors): array
    {
        $flattened = [];

        foreach ($validationErrors as $field => $value) {
            $messages = is_array($value) ? $value : [$value];

            $texts = [];
            foreach ($messages as $message) {
                $texts[] = is_scalar($message) ? (string) $message : (json_encode($message) ?: '[unavailable]');
            }

            $flattened[(string) $field] = mb_substr(implode(' ', $texts), 0, 500);
        }

        return $flattened;
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

    private static function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        $normalised = mb_strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalised, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
