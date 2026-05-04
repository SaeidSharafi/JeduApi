<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class SpotPlayerService
{
    public function issueLicense(string $spotId, User $user): array
    {
        $response = $this->request()->post('', [
            'spot_id'       => $spotId,
            'mobile'        => $user->phone,
            'name'          => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email'         => $user->email,
            'national_code' => $user->civil_id,
            'sandbox'       => (bool) config('services.spotplayer.sandbox', false),
        ]);

        if ($response->failed()) {
            throw new ExternalProvisioningException('SpotPlayer provisioning request failed.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new ExternalProvisioningException('SpotPlayer invalid response format.');
        }

        if ((isset($data['status']) && $data['status'] === false) || isset($data['error'])) {
            $message = (string) ($data['message'] ?? $data['error'] ?? 'SpotPlayer returned an error response.');
            throw new ExternalProvisioningException($message);
        }

        return [
            'license_key' => data_get($data, 'license_key') ?? data_get($data, 'data.license_key'),
            'player_url'  => data_get($data, 'player_url')  ?? data_get($data, 'data.player_url'),
            'raw'         => $data,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.spotplayer.endpoint'))
            ->timeout((int) config('services.spotplayer.timeout', 15))
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => (string) config('services.spotplayer.api_key'),
            ]);
    }
}
