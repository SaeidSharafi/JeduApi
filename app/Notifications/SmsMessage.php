<?php

declare(strict_types=1);

namespace App\Notifications;

final class SmsMessage
{
    public ?string $content = null;

    public ?string $pattern = null;

    public array $parameters = [];

    public string $type = 'custom';

    /**
     * Set the message content for a standard SMS.
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the pattern code for a pattern-based SMS.
     */
    public function pattern(string $pattern, array $parameters = []): self
    {
        $this->pattern    = $pattern;
        $this->parameters = $parameters;
        $this->type       = 'pattern';

        return $this;
    }

    /**
     * Set the type of the SMS (e.g., 'OTP', 'marketing').
     * This is useful for logging and analytics.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
