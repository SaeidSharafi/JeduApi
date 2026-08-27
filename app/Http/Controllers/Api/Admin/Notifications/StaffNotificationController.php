<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Notifications;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Notification\NotificationListQueryData;
use App\Data\Admin\Notification\StaffNotificationData;
use App\Data\Admin\Notification\UnreadNotificationCountData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @group Admin - Notifications
 *
 * @authenticated
 */
final class StaffNotificationController extends Controller
{
    /**
     * List the authenticated staff member's notifications.
     *
     * @responseFile 200 resources/responses/admin/notification/index.json
     */
    public function index(NotificationListQueryData $query): ApiResponseInterface
    {
        $unread        = $query->filter['unread'] ?? null;
        $notifications = auth('staff')->user()->notifications()->latest()->when(
            array_key_exists('unread', $query->filter ?? []),
            function (Builder $builder) use ($unread): void {
                filter_var($unread, FILTER_VALIDATE_BOOLEAN)
                    ? $builder->whereNull('read_at')
                    : $builder->whereNotNull('read_at');
            }
        )->paginate(request()->integer('per_page', config('app.page_size')))->withQueryString();

        return apiResponse()->success(StaffNotificationData::collect($notifications));
    }

    /**
     * Get the authenticated staff member's unread notification count.
     *
     * @responseFile 200 resources/responses/admin/notification/unread-count.json
     */
    public function unreadCount(): ApiResponseInterface
    {
        return apiResponse()->success(new UnreadNotificationCountData(auth('staff')->user()->unreadNotifications()->count()));
    }

    /**
     * Mark one of the authenticated staff member's notifications as read.
     *
     * @response 204
     */
    public function read(string $notification): JsonResponse
    {
        $this->ownedNotification($notification)->markAsRead();

        return apiResponse()->noContentJson();
    }

    /**
     * Mark all notifications belonging to the authenticated staff member as read.
     *
     * @response 204
     */
    public function readAll(): JsonResponse
    {
        auth('staff')->user()->unreadNotifications()->update(['read_at' => now()]);

        return apiResponse()->noContentJson();
    }

    private function ownedNotification(string $notification): DatabaseNotification
    {
        return auth('staff')->user()->notifications()->whereKey($notification)->firstOrFail();
    }
}
