<?php

declare(strict_types=1);

namespace App\Enums\Sms;

/**
 * The transactional SMS notification options the admin panel can configure.
 *
 * Adding an option is a backend-only change: add a case here, its
 * `config/sms.php` block and its `sms.notifications.<value>.label` translation,
 * and the read endpoint returns it in declaration order.
 */
enum SmsNotificationOptionEnum: string
{
    case OTP                      = 'otp';
    case REFUND_COMPLETED         = 'refund_completed';
    case ORDER_PAID               = 'order_paid';
    case ENROLLMENT_READY         = 'enrollment_ready';
    case WALLET_CAMPAIGN_CREDITED = 'wallet_campaign_credited';

    /**
     * The option governing an outgoing message type, or `null` when the type is
     * not configurable — an ad-hoc send stays ungated.
     */
    public static function fromLogType(string $logType): ?self
    {
        foreach (self::cases() as $option) {
            if ($option->logType() === $logType) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Configuration-derived defaults, used until the option is saved.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return config('sms.notifications.'.$this->value, []);
    }

    /**
     * Merge one stored option over the configuration defaults, field by field.
     *
     * The stored row wins where it declares a field, so a never-saved option
     * still resolves a complete `enabled` / `pattern_code` pair. Stored keys the
     * configuration does not declare are dropped: only the documented fields
     * are part of the contract.
     *
     * @return array{enabled: bool, pattern_code: string}
     */
    public function resolve(mixed $storedOption): array
    {
        $defaults     = $this->defaultConfig();
        $storedOption = is_array($storedOption) ? array_intersect_key($storedOption, $defaults) : [];
        $settings     = array_merge($defaults, $storedOption);

        return [
            'enabled'      => (bool) ($settings['enabled'] ?? false),
            'pattern_code' => (string) ($settings['pattern_code'] ?? ''),
        ];
    }

    /**
     * Translated display label for the admin panel.
     */
    public function label(): string
    {
        return __("sms.notifications.{$this->value}.label");
    }

    /**
     * The outgoing `SmsMessage` type this option governs.
     *
     * Matches the contract's `log_type` column, so the send path can find the
     * option an outgoing message belongs to from the type it already carries.
     */
    public function logType(): string
    {
        return match ($this) {
            self::OTP                      => 'OTP',
            self::REFUND_COMPLETED         => 'REFUND',
            self::ORDER_PAID               => 'ORDER',
            self::ENROLLMENT_READY         => 'ENROLLMENT',
            self::WALLET_CAMPAIGN_CREDITED => 'WALLET',
        };
    }

    /**
     * Whether the resolved settings let this option deliver.
     *
     * The refund does not require a pattern, so an empty code still counts as
     * configured for it; every other option needs the code the provider
     * requires. Shared by the admin `state.configured` badge and the runtime
     * option gate so the two cannot disagree.
     *
     * @param  array{enabled: bool, pattern_code: string}  $settings
     */
    public function isConfigured(array $settings): bool
    {
        return ! $this->requiresPattern() || $settings['pattern_code'] !== '';
    }

    /**
     * Whether the option can only be sent through a provider pattern.
     *
     * Options that need no pattern fall back to the provider's free-text send,
     * so an empty `pattern_code` still leaves them ready.
     */
    public function requiresPattern(): bool
    {
        return match ($this) {
            self::REFUND_COMPLETED => false,
            default                => true,
        };
    }
}
