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
