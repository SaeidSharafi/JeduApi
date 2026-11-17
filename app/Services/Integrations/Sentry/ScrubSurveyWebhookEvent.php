<?php

declare(strict_types=1);

namespace App\Services\Integrations\Sentry;

use Sentry\Event;
use Sentry\EventHint;

final class ScrubSurveyWebhookEvent
{
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        return self::scrubRequest($event);
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): ?Event
    {
        return self::scrubRequest($event);
    }

    private static function scrubRequest(Event $event): Event
    {
        $request = $event->getRequest();
        $path    = parse_url((string) ($request['url'] ?? ''), PHP_URL_PATH);
        $path    = is_string($path) ? mb_rtrim(rawurldecode($path), '/') : '';

        if ($path !== '/api/v1/shop/webhooks/porsline-surveys') {
            return $event;
        }

        unset($request['data']);

        foreach (array_keys($request['headers'] ?? []) as $header) {
            if (is_string($header) && mb_strtolower($header) === 'x-survey-webhook-secret') {
                unset($request['headers'][$header]);
            }
        }

        return $event->setRequest($request);
    }
}
