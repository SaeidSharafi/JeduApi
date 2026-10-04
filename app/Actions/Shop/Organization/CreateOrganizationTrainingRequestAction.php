<?php

declare(strict_types=1);

namespace App\Actions\Shop\Organization;

use App\Actions\Shop\UploadFileAction;
use App\Data\Shop\Organization\OrganizationTrainingRequestCreateData;
use App\Enums\PermissionEnum;
use App\Models\OrganizationPage;
use App\Models\OrganizationTrainingRequest;
use App\Models\Staff;
use App\Notifications\Admin\OrganizationTrainingRequestSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final readonly class CreateOrganizationTrainingRequestAction
{
    public function __construct(private UploadFileAction $uploadFile) {}

    public function handle(OrganizationTrainingRequestCreateData $data): OrganizationTrainingRequest
    {
        $request = DB::transaction(function () use ($data): OrganizationTrainingRequest {
            $page = OrganizationPage::query()
                ->singleton()
                ->lockForUpdate()
                ->firstOrFail();
            $vendor = $page->vendor()->first();

            $trainingRequest = OrganizationTrainingRequest::query()->create([
                'uuid'                   => (string) Str::uuid7(),
                'first_name'             => mb_trim($data->first_name),
                'last_name'              => mb_trim($data->last_name),
                'phone'                  => mb_trim($data->phone),
                'position'               => mb_trim($data->position),
                'organization_name'      => mb_trim($data->organization_name),
                'requested_course_names' => self::normalizeCourseNames($data->requested_course_names ?? []),
                'notes'                  => $data->notes !== null ? mb_trim($data->notes) : null,
                'vendor_id'              => $vendor?->id,
                'vendor_snapshot'        => $vendor ? ['id' => $vendor->id, 'name' => $vendor->name] : null,
            ]);

            if ($data->attachment !== null) {
                $attachment = $this->uploadFile->handle($data->attachment, false);
                $trainingRequest->attachMedia($attachment, 'attachment');
            }

            return $trainingRequest;
        });

        $recipients = Staff::query()
            ->where('is_banned', false)
            ->permission(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW_ANY->value)
            ->get();

        Notification::send($recipients, new OrganizationTrainingRequestSubmittedNotification($request));

        return $request;
    }

    /**
     * @param  array<int, string>  $courseNames
     * @return array<int, string>
     */
    private static function normalizeCourseNames(array $courseNames): array
    {
        $normalized = [];
        $seen       = [];

        foreach ($courseNames as $courseName) {
            $courseName = preg_replace('/\s+/u', ' ', mb_trim($courseName)) ?? mb_trim($courseName);
            $key        = mb_strtolower($courseName);

            if ($courseName === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key]   = true;
            $normalized[] = $courseName;
        }

        return $normalized;
    }
}
