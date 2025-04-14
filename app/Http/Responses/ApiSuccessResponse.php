<?php

namespace App\Http\Responses;

use App\Contracts\ApiResponseInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class ApiSuccessResponse implements ApiResponseInterface
{
    public function __construct(
        private mixed $message,
        private mixed $data,
        private int $code = Response::HTTP_OK,
        private array $metadata = [],
        private array $headers = []
    ) {}

    /**
     * {@inheritDoc}
     */
    public function toResponse($request): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            [
                'message' => $this->message,
                'data' => $this->data,
                'metadata' => $this->metadata,
            ],
            $this->code,
            $this->headers
        );
    }
}
