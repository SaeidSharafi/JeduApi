<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

use App\Exceptions\Gateway\DigipayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class DigipayAuthenticator
{
    private const CACHE_KEY = 'digipay_access_token';

    public function __construct(
        private DigipayConfigRepository $config,
    ) {}

    public function getAccessToken(): string
    {
        if ($token = Cache::get(self::CACHE_KEY)) {
            return $token;
        }

        return $this->fetchAndCacheToken();
    }

    public function clearToken(): void
    {
        Cache::forget(self::CACHE_KEY);
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
            throw new DigipayException('Digipay authentication failed', $response->status());
        }

        $buffer = config('payments.digipay.token_cache.buffer', 300);
        $ttl    = max(1, ((int) ($response['expires_in'] ?? 3600)) - $buffer);

        Cache::put(self::CACHE_KEY, $response['access_token'], $ttl);

        return $response['access_token'];
    }
}
