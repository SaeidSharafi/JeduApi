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
        $token = $this->cache->get(CacheKey::DigipayAccessToken);

        if (is_string($token) && $token !== '') {
            return $token;
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

        $this->cache->put(CacheKey::DigipayAccessToken, [], $response['access_token']);

        return $response['access_token'];
    }
}
