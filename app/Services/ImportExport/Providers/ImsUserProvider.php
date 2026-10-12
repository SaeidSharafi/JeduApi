<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Providers;

use App\Contracts\ImportExport\UserProvisioningProvider;
use App\Contracts\Integrations\ImsClientContract;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;

final readonly class ImsUserProvider implements UserProvisioningProvider
{
    public function __construct(private ImsClientContract $client) {}

    public function ensureUser(User $user): void
    {
        if (! $this->client->isEnabled()) {
            throw new UnrecoverableProvisioningException('Provider is disabled.');
        }
        $this->client->assertConfigured();
        $this->client->storeStudent([
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'phone'            => $user->phone,
            'email'            => $user->email,
            'civil_id'         => $user->civil_id,
            'civil_id_type'    => $user->civil_id_type,
            'father_name'      => $user->father_name,
            'gender'           => $user->gender?->toImsValue(),
            'field_of_study'   => $user->field_of_study,
            'education_level'  => $user->education_level?->value,
            'education_status' => $user->education_status?->value,
            'date_of_birth'    => $user->date_of_birth?->format('Y-m-d'),
            'update_student'   => false,
        ]);
    }

    public function canReplay(): bool
    {
        return false;
    }
}
