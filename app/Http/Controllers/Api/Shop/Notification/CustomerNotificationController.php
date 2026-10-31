<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Notification;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Notification\CustomerNotificationData;
use App\Data\Shop\Notification\NotificationListQueryData;
use App\Data\Shop\Notification\UnreadNotificationCountData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Notifications
 *
 * @authenticated
 */
final class CustomerNotificationController extends Controller
{
    /**
     * List the authenticated customer's notifications.
     *
     * @responseFile 200 resources/responses/shop/notification/index.json
     */
    public function index(NotificationListQueryData $query): ApiResponseInterface
    {
        $unread        = $query->filter['unread'] ?? null;
        $notifications = Auth::guard('user')->user()->notifications()->latest()->when(
            array_key_exists('unread', $query->filter ?? []),
            function (Builder $builder) use ($unread): void {
                filter_var($unread, FILTER_VALIDATE_BOOLEAN)
                    ? $builder->whereNull('read_at')
                    : $builder->whereNotNull('read_at');
            }
        )->paginate($query->per_page ?? config('app.page_size'))->withQueryString();

        return apiResponse()->success($notifications->through(CustomerNotificationData::fromModel(...)));
    }

    /**
     * Get the authenticated customer's unread notification count.
     *
     * @responseFile 200 resources/responses/shop/notification/unread-count.json
     */
    public function unreadCount(): ApiResponseInterface
    {
        return apiResponse()->success(new UnreadNotificationCountData(Auth::guard('user')->user()->unreadNotifications()->count()));
    }

    /**
     * Mark one of the authenticated customer's notifications as read.
     *
     * @response 204
     */
    public function read(string $notification): JsonResponse
    {
        $this->ownedNotification($notification)->markAsRead();

        return apiResponse()->noContentJson();
    }

    /**
     * Mark all of the authenticated customer's notifications as read.
     *
     * @response 204
     */
    public function readAll(): JsonResponse
    {
        Auth::guard('user')->user()->unreadNotifications()->update(['read_at' => now()]);

        return apiResponse()->noContentJson();
    }

    private function ownedNotification(string $notification): DatabaseNotification
    {
        return Auth::guard('user')->user()->notifications()->whereKey($notification)->firstOrFail();
    }
}
