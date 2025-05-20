<?php

namespace app;

use Illuminate\Http\Request;
use Spatie\WebhookClient\Exceptions\InvalidConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
class GitHubSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $headerSignature = $request->header($config->signatureHeaderName); // e.g., "sha256=7bc0..."

        if (! $headerSignature) {
            return false;
        }

        $signingSecret = $config->signingSecret;
        if (empty($signingSecret)) {
            throw InvalidConfig::signingSecretNotSet();
        }

        if (strpos($headerSignature, '=') === false) {
            // Signature format is unexpected (missing '=')
            return false;
        }
        list($usedAlgorithm, $signatureValueFromHeader) = explode('=', $headerSignature, 2);

        // Optional: verify $usedAlgorithm is 'sha256' if you want to be strict
        if ($usedAlgorithm !== 'sha256') {
            // Log::warning("Unexpected algorithm used: {$usedAlgorithm}");
            // return false; // Or proceed if you don't care about the algo name itself
        }

        $computedSignature = hash_hmac('sha256', $request->getContent(), $signingSecret);

        return hash_equals($computedSignature, $signatureValueFromHeader);
    }
}
