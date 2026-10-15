<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Clears the storefront's Data Cache through its revalidation webhook.
 *
 * `POST {storefront}/api/revalidate` with the shared secret and `{"tags": [...]}`
 * drops the Next Data Cache entries carrying those tags. The storefront refuses
 * the whole request when it does not recognise a tag, so the caller's vocabulary
 * must come from {@see \App\Enums\System\StorefrontTag}.
 *
 * The storefront's own caching docs (`docs/caching-plan.md` §5) are the contract:
 * `200` cleared, `400` a payload problem (`rejected` names it), `401` the secrets
 * differ, `503` the storefront is missing its `REVALIDATE_SECRET`, `404` the
 * running storefront build predates the endpoint, `405` the wrong method. Only
 * transport failures and 5xx other than `503` are worth retrying.
 *
 * An unconfigured environment (no URL or no secret) is a no-op: local development
 * and the test suite must not reach out, and a half-deployed environment degrades
 * to TTL-based freshness instead of erroring.
 */
final class StorefrontRevalidationService
{
    private const string REVALIDATE_PATH = '/api/revalidate';

    /**
     * Seconds to wait for the storefront to accept the connection.
     */
    private const int CONNECT_TIMEOUT_SECONDS = 2;

    public function isConfigured(): bool
    {
        return $this->url() !== null && $this->secret() !== null;
    }

    /**
     * @param  list<string>  $tags
     * @return bool Whether the storefront cleared the tags. False means nothing was
     *              sent (unconfigured) or the storefront refused the payload — both
     *              already logged, so a queued caller can ignore this and an operator
     *              command can turn it into an exit code.
     *
     * @throws ConnectionException
     * @throws RuntimeException when the storefront answers with a retryable failure
     */
    public function revalidate(array $tags): bool
    {
        if ($tags === []) {
            return true;
        }

        if (! $this->isConfigured()) {
            Log::debug('Storefront cache revalidation is not configured, skipping.', ['tags' => $tags]);

            return false;
        }

        try {
            $response = Http::baseUrl($this->url())
                ->timeout((int) config('storefront.timeout', 3))
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->acceptJson()
                ->withHeaders(['x-revalidate-secret' => $this->secret()])
                ->post(self::REVALIDATE_PATH, ['tags' => $tags]);
        } catch (ConnectionException $exception) {
            // Rethrown so the queue retries, but logged first: the raw transport
            // exception says which URL failed and nothing about what was lost.
            Log::error('Storefront cache revalidation could not reach the storefront.', [
                'url'     => $this->url(),
                'tags'    => $tags,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($response->successful()) {
            Log::debug('Storefront cache revalidation cleared.', ['status' => $response->status(), 'tags' => $tags]);

            return true;
        }

        if ($this->isRetryable($response)) {
            throw new RuntimeException(sprintf(
                'Storefront cache revalidation failed with status %d.',
                $response->status(),
            ));
        }

        Log::error('Storefront cache revalidation rejected.', [
            'status'   => $response->status(),
            'error'    => $response->json('error'),
            'rejected' => $response->json('rejected'),
            'tags'     => $tags,
        ]);

        return false;
    }

    /**
     * A 5xx other than 503 may be transient. The storefront's 503 means its own
     * `REVALIDATE_SECRET` is unset — a deployment problem that a retry cannot fix.
     */
    private function isRetryable(Response $response): bool
    {
        return $response->status() >= 500 && $response->status() !== 503;
    }

    private function url(): ?string
    {
        $url = config('storefront.url');

        return is_string($url) && $url !== '' ? mb_rtrim($url, '/') : null;
    }

    private function secret(): ?string
    {
        $secret = config('storefront.revalidate_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
