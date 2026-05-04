<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final readonly class ImsService
{
    public function provisionEnrollment(array $payload): array
    {
        $response = $this->request()
            ->post(config('services.ims.enrollments_endpoint'), $payload);

        if ($response->failed()) {
            throw new ExternalProvisioningException('IMS provisioning request failed.');
        }

        $responseData = $response->json();
        $status       = data_get($responseData, 'status');
        $message      = data_get($responseData, 'message');

        if ($status !== true || $message !== 'ok') {
            $errors       = Arr::wrap(data_get($responseData, 'errors', []));
            $errorMessage = ! empty($errors)
                ? implode('; ', array_values($errors))
                : 'IMS provisioning response was not successful.';

            throw new ExternalProvisioningException($errorMessage);
        }

        return is_array($responseData) ? $responseData : [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.ims.base_url'))
            ->timeout((int) config('services.ims.timeout', 15))
            ->acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                (string) config('services.ims.api_key_header', 'X-API-KEY') => (string) config('services.ims.api_key'),
            ]);
    }
}
