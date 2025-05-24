<?php

declare(strict_types=1);

use App\SignatureValidator\GitHubSignatureValidator;
use Illuminate\Http\Request;
use Spatie\WebhookClient\Exceptions\InvalidConfig;

describe('GitHubSignatureValidator', function (): void {
    beforeEach(function (): void {
        $this->makeConfig = function ($secret, $header) {
            $config                      = Mockery::mock(Spatie\WebhookClient\WebhookConfig::class);
            $config->signingSecret       = $secret;
            $config->signatureHeaderName = $header;

            return $config;
        };
    });

    it('returns true for valid signature', function (): void {
        $secret    = 'test_secret';
        $payload   = '{"foo":"bar"}';
        $signature = hash_hmac('sha256', $payload, $secret);
        $header    = 'sha256='.$signature;

        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $header);

        $config = ($this->makeConfig)($secret, 'X-Hub-Signature-256');

        $validator = new GitHubSignatureValidator();
        expect($validator->isValid($request, $config))->toBeTrue();
    });

    it('returns false for missing signature header', function (): void {
        $secret    = 'test_secret';
        $payload   = '{"foo":"bar"}';
        $request   = Request::create('/', 'POST', [], [], [], [], $payload);
        $config    = ($this->makeConfig)($secret, 'X-Hub-Signature-256');
        $validator = new GitHubSignatureValidator();
        expect($validator->isValid($request, $config))->toBeFalse();
    });

    it('returns false for invalid signature', function (): void {
        $secret  = 'test_secret';
        $payload = '{"foo":"bar"}';
        $header  = 'sha256=invalidsignature';
        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $header);
        $config    = ($this->makeConfig)($secret, 'X-Hub-Signature-256');
        $validator = new GitHubSignatureValidator();
        expect($validator->isValid($request, $config))->toBeFalse();
    });

    it('throws if signing secret is missing', function (): void {
        $payload   = '{"foo":"bar"}';
        $signature = hash_hmac('sha256', $payload, 'irrelevant');
        $header    = 'sha256='.$signature;
        $request   = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $header);
        $config    = ($this->makeConfig)('', 'X-Hub-Signature-256');
        $validator = new GitHubSignatureValidator();
        expect(fn (): bool => $validator->isValid($request, $config))->toThrow(InvalidConfig::class);
    });

    it('returns false for malformed header', function (): void {
        $secret  = 'test_secret';
        $payload = '{"foo":"bar"}';
        $header  = 'malformedheader';
        $request = Request::create('/', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Hub-Signature-256', $header);
        $config    = ($this->makeConfig)($secret, 'X-Hub-Signature-256');
        $validator = new GitHubSignatureValidator();
        expect($validator->isValid($request, $config))->toBeFalse();
    });
});
