<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Exceptions\Gateway\DigipayException;
use Illuminate\Support\Facades\Http;

final class DigipayAuthenticator
{
    public function __construct(
        private DigipayConfigRepository $config,
        private CacheStore $cache,
    ) {}

    public function getAccessToken(): string
    {
        $cached = $this->cache->get(CacheKey::DigipayAccessToken);

        // The payload carries its own expiry: the registry lifetime is only an upper
        // bound, and a token must never be served after the gateway expires it.
        if (is_array($cached)
            && is_string($cached['token'] ?? null)
            && $cached['token'] !== ''
            && (int) ($cached['expires_at'] ?? 0) > time()) {
            return $cached['token'];
        }

        return $this->fetchAndCacheToken();
    }

    private function fetchAndCacheToken(): string
    {
        $credentials = base64_encode(
            $this->config->getClientId().':'.$this->config->getClientSecret()
        );

        $response = Http::timeout($this->config->getTimeout())
            ->withHeaders(['Authorization' => 'Basic '.$credentials])
            ->asForm()
            ->post($this->config->getBaseUrl().config('payments.digipay.paths.oauth_token'), [
                'username'   => $this->config->getUsername(),
                'password'   => $this->config->getPassword(),
                'grant_type' => 'password',
            ]);

        if ($response->failed() || ! isset($response['access_token'])) {
            throw new DigipayException(__('payment_gateways.digipay.errors.authentication_failed'), $response->status());
        }

        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        $buffer    = (int) config('payments.digipay.token_cache.buffer', 300);

        $this->cache->put(CacheKey::DigipayAccessToken, [], [
            'token'      => (string) $response['access_token'],
            'expires_at' => time() + max(1, $expiresIn - $buffer),
        ]);

        return (string) $response['access_token'];
    }
}
