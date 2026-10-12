<?php

declare(strict_types=1);

use App\Contracts\Integrations\ImsClientContract;
use App\Enums\User\GenderEnum;
use App\Models\User;
use App\Services\ImportExport\Providers\ImsUserProvider;

mutates(ImsUserProvider::class);

it('sends the IMS integer gender and no external user id', function (GenderEnum $gender, int $expected): void {
    $user = User::factory()->create([
        'gender'           => $gender,
        'education_level'  => null,
        'education_status' => null,
    ]);

    $client = $this->mock(ImsClientContract::class);
    $client->shouldReceive('isEnabled')->andReturnTrue();
    $client->shouldReceive('assertConfigured');
    $client->shouldReceive('storeStudent')->once()->withArgs(function (array $payload) use ($expected, $user): bool {
        return $payload['gender'] === $expected
            && ! array_key_exists('external_user_id', $payload)
            && $payload['education_level'] === null
            && $payload['phone']           === $user->phone;
    });

    (new ImsUserProvider($client))->ensureUser($user);
})->with([
    'male'   => [GenderEnum::MALE, 1],
    'female' => [GenderEnum::FEMALE, 2],
]);
