<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Plank\Mediable\Jobs\CreateImageVariants;
use Plank\Mediable\Media;

/**
 * Queues variant generation for the media that can actually be decoded.
 *
 * The image aggregate type covers more formats than the `thumb` variant can handle:
 * `ico` (and `heic` on builds without HEIF support) is reported as `image` by
 * mediable, but Imagick cannot decode it, so the queued job failed with
 * Intervention's ImageDecoderException.
 *
 * The check therefore uses the MIME type sniffed from the file contents instead of
 * the client-supplied extension, and the media is skipped when no variant definition
 * can be applied to it.
 */
final class DispatchImageVariantsAction
{
    /**
     * Content-sniffed MIME types the variant pipeline can decode and re-encode.
     *
     * @var list<string>
     */
    private const DECODABLE_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function handle(Media $media, string $variantName): void
    {
        if (! $this->isDecodableImage($media)) {
            return;
        }

        CreateImageVariants::dispatch($media, $variantName);
    }

    private function isDecodableImage(Media $media): bool
    {
        return in_array(
            mb_strtolower((string) $media->mime_type),
            self::DECODABLE_IMAGE_MIME_TYPES,
            true
        );
    }
}
