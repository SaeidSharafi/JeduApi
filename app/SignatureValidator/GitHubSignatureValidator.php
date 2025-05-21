<?php

declare(strict_types=1);

namespace App\SignatureValidator;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Exceptions\InvalidConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

final class GitHubSignatureValidator implements SignatureValidator
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

        if (mb_strpos($headerSignature, '=') === false) {
            return false;
        }
        [$usedAlgorithm, $signatureValueFromHeader] = explode('=', $headerSignature, 2);

        $computedSignature = hash_hmac('sha256', $request->getContent(), $signingSecret);
        $result = hash_equals($computedSignature, $signatureValueFromHeader);
        if (! $result) {
            Log::channel('deployment')->debug('Signature mismatch', [
                'computed' => $computedSignature,
                'header' => $signatureValueFromHeader,
            ]);
        }

        return $result;
    }
}
