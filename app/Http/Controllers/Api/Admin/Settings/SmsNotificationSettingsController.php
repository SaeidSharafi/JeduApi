<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Actions\Admin\Settings\Sms\BuildSmsNotificationsAction;
use App\Actions\Admin\Settings\Sms\UpdateSmsNotificationsAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\Sms\UpdateSmsNotificationsData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
final class SmsNotificationSettingsController extends Controller
{
    public function __construct(
        private readonly BuildSmsNotificationsAction $buildNotifications,
        private readonly UpdateSmsNotificationsAction $updateNotifications,
    ) {}

    /**
     * List SMS notification options.
     *
     * Returns the shared field schema plus every configurable notification option
     * in a fixed order, each with its translated label, effective settings and
     * computed state. Values come from the stored `sms_notifications` setting
     * when present and from the SMS configuration file otherwise, so a
     * never-saved option still returns a complete `enabled` / `pattern_code`
     * pair.
     *
     * Each option contains:
     * - `key` (string): Option identifier.
     * - `label` (string): Localized display label.
     * - `state` (object): `enabled`, `configured` (the pattern the option requires is present) and `ready` (`enabled && configured`).
     * - `settings` (object): Effective `enabled` and `pattern_code`.
     *
     * @responseFile 200 resources/responses/admin/settings/sms-notifications/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return apiResponse()->success($this->buildNotifications->handle());
    }

    /**
     * Update SMS notification options.
     *
     * The body carries an `options` map keyed by option key. Each provided option
     * must carry both `enabled` and `pattern_code`; an option omitted from the
     * map is left untouched, so a partial save cannot disable the rest.
     * Disabling an option with an empty `pattern_code` keeps the stored code, and
     * an unknown option key is rejected with a `422`.
     *
     * @responseFile 200 resources/responses/admin/settings/sms-notifications/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(UpdateSmsNotificationsData $request): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        return apiResponse()->success($this->updateNotifications->handle($request));
    }
}
