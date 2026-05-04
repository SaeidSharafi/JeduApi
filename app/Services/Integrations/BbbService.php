<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class BbbService
{
    public function createMeeting(string $meetingId, string $name, string $attendeePassword, string $moderatorPassword): void
    {
        $response = $this->request()->post('/create', [
            'meetingID'   => $meetingId,
            'name'        => $name,
            'attendeePW'  => $attendeePassword,
            'moderatorPW' => $moderatorPassword,
        ]);

        if ($response->failed()) {
            throw new ExternalProvisioningException('BBB create meeting request failed.');
        }
    }

    public function buildJoinUrl(string $meetingId, string $fullName, string $password): string
    {
        $base    = rtrim((string) config('services.bbb.base_url'), '/');
        $apiPath = trim((string) config('services.bbb.api_path', '/bigbluebutton/api'), '/');

        return sprintf('%s/%s/join?meetingID=%s&fullName=%s&password=%s',
            $base,
            $apiPath,
            urlencode($meetingId),
            urlencode($fullName),
            urlencode($password)
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.bbb.base_url'))
            ->timeout((int) config('services.bbb.timeout', 15));
    }
}
