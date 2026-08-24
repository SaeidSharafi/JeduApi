<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Contracts\ApiResponseInterface;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\MediaTagEnum;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Shop - Student - Digital Assets
 *
 * @authenticated user
 */
final class DigitalAssetDownloadController extends Controller
{
    /**
     * Download a digital asset file.
     *
     * Streams the file associated with the given digital asset for the authenticated user's enrollment.
     *
     * @response 200 <<binary>> file
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(Enrollment $enrollment, DigitalAsset $digitalAsset): ApiResponseInterface|StreamedResponse|RedirectResponse
    {
        $user = auth()->user();

        // 1. Verify ownership
        if ($enrollment->customer_id !== $user->id) {
            return apiResponse()->forbidden(__('messages.digital_asset.no_access'));
        }

        // 2. Verify enrollment is ACTIVE
        if ($enrollment->enrollment_status !== EnrollmentStatusEnum::ACTIVE) {
            return apiResponse()->forbidden(__('messages.digital_asset.enrollment_not_active'));
        }

        // 3. Verify the digital asset belongs to this enrollment's productable context
        $productable = $enrollment->productDeliveryOption->product->productable;

        if ($productable instanceof DigitalAsset) {
            if ($productable->id !== $digitalAsset->id) {
                return apiResponse()->notFound(__('messages.digital_asset.not_found_for_enrollment'));
            }
        } elseif (method_exists($productable, 'digitalAssets')) {
            $attached = $productable->digitalAssets()->where('digital_assets.id', $digitalAsset->id)->exists();
            if (! $attached) {
                return apiResponse()->notFound(__('messages.digital_asset.not_found_for_enrollment'));
            }
        } else {
            return apiResponse()->notFound(__('messages.digital_asset.not_found_for_enrollment'));
        }

        // 4. Load downloadable media — prefer 'main' tag
        $digitalAsset->loadMediaWithVariantsMatchAll([MediaTagEnum::MAIN->value]);
        $media = $digitalAsset->getMedia(MediaTagEnum::MAIN->value)->first();

        if (! $media) {
            return apiResponse()->notFound(__('messages.digital_asset.no_downloadable_file'));
        }

        // 5. Deliver the file — s3 via 302 redirect (resumable, 7-day window), local via stream
        $disk = Storage::disk($media->disk);
        $path = $media->getDiskPath();

        if (! $disk->exists($path)) {
            return apiResponse()->notFound(__('messages.file.storage_not_found'));
        }

        if ($media->disk === 's3') {
            $expiryDays = (int) config('filesystems.disks.s3.temporary_url_expiry_days', 7);
            $expiryDays = max(1, min($expiryDays, 7));
            $expiresAt  = now()->addDays($expiryDays);

            $url = $disk->temporaryUrl($path, $expiresAt, [
                'ResponseContentType'        => $media->mime_type,
                'ResponseContentDisposition' => 'attachment; filename="'.$media->filename.'.'.$media->extension.'"',
            ]);

            return redirect()->away($url, 302);
        }

        return $disk->download($path, "{$media->filename}.{$media->extension}", [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
