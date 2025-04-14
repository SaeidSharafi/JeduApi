<?php

namespace App\Http\Responses;

use App\Contracts\ApiResponseInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class ApiFailResponse implements ApiResponseInterface
{
    public function __construct(
        private mixed $message,
        private mixed $errors,
        private int $code = Response::HTTP_BAD_REQUEST,
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
                'errors' => $this->errors,
                'metadata' => $this->metadata,
            ],
            $this->code,
            $this->headers
        );
    }
}
