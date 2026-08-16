<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Data\Shop\Customer\UpdateProfileData;
use App\Events\ProfileCompletedEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProfileAction
{
    /**
     * Execute the action.
     */
    public function handle(UpdateProfileData $data, User $user): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $wasCompleted = $user->profileCompleted();

            $updateData = [
                'first_name'       => $data->first_name,
                'last_name'        => $data->last_name,
                'email'            => $data->email,
                'phone2'           => $data->phone2,
                'date_of_birth'    => $data->date_of_birth,
                'gender'           => $data->gender,
                'father_name'      => $data->father_name,
                'education_level'  => $data->education_level,
                'field_of_study'   => $data->field_of_study,
                'education_status' => $data->education_status,
            ];

            if (! $user->civil_id) {
                $updateData['civil_id']      = $data->civil_id;
                $updateData['civil_id_type'] = $data->civil_id_type;
            }

            $user->update($updateData);

            $user = $user->fresh();

            // Fire once, on the first transition from incomplete to complete.
            if (! $wasCompleted && $user->profileCompleted()) {
                ProfileCompletedEvent::dispatch($user);
            }

            return $user;
        });
    }
}
