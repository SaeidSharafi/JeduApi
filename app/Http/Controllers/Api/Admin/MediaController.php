<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Data\Transformer\MediaData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Plank\Mediable\Facades\MediaUploader;

class MediaController extends Controller
{
    /**
     * Upload a media file and return its info.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'alt' => 'nullable|string|max:255',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $alt = (string)$request->string('alt');
        $media = MediaUploader::fromSource($file)
            ->toDisk(config('mediable.default_disk', 'public'))
            ->withAltAttribute($alt)
            ->onDuplicateIncrement()
            ->upload();


        return response()->json([
            'data' => MediaData::fromModel($media),
        ], 201);
    }
}
