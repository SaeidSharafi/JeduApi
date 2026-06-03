<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Contracts\ApiResponseInterface;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\MediaTagEnum;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Shop - Student Dash - My Digital Assets
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
    public function __invoke(Enrollment $enrollment, DigitalAsset $digitalAsset): ApiResponseInterface|StreamedResponse
    {
        $user = auth()->user();

        // 1. Verify ownership
        if ($enrollment->customer_id !== $user->id) {
            return response()->forbidden('You do not have access to this enrollment.');
        }

        // 2. Verify enrollment is ACTIVE
        if ($enrollment->enrollment_status !== EnrollmentStatusEnum::ACTIVE) {
            return response()->forbidden('Your enrollment is not active.');
        }

        // 3. Verify the digital asset belongs to this enrollment's productable context
        $productable = $enrollment->productDeliveryOption->product->productable;

        if ($productable instanceof DigitalAsset) {
            if ($productable->id !== $digitalAsset->id) {
                return response()->notFound('Digital asset not found for this enrollment.');
            }
        } elseif (method_exists($productable, 'digitalAssets')) {
            $attached = $productable->digitalAssets()->where('digital_assets.id', $digitalAsset->id)->exists();
            if (! $attached) {
                return response()->notFound('Digital asset not found for this enrollment.');
            }
        } else {
            return response()->notFound('Digital asset not found for this enrollment.');
        }

        // 4. Load downloadable media — prefer 'main' tag
        $digitalAsset->loadMediaWithVariantsMatchAll([MediaTagEnum::MAIN->value]);
        $media = $digitalAsset->getMedia(MediaTagEnum::MAIN->value)->first();

        if (! $media) {
            return response()->notFound('No downloadable file is available for this digital asset.');
        }

        // 5. Stream the file
        $disk = Storage::disk($media->disk);
        $path = $media->getDiskPath();

        if (! $disk->exists($path)) {
            return response()->notFound('File not found on storage.');
        }

        return $disk->download($path, "{$media->filename}.{$media->extension}", [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
