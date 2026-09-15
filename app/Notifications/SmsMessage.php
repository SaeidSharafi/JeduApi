<?php

declare(strict_types=1);

namespace App\Notifications;

final class SmsMessage
{
    public ?string $content = null;

    /** @var array<string, mixed> */
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
     * Set the variables a configured provider pattern may reference.
     *
     * The pattern code itself comes from the notification option settings, not
     * from the message, so the notification only declares its variables.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function parameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Set the type of the SMS (e.g., 'OTP', 'marketing').
     * This is useful for logging and analytics, and maps the message to its
     * configurable notification option.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
