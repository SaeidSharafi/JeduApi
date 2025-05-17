<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Contracts\ApiResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApiFailResponse implements ApiResponseInterface
{
    /**
     * Create a new instance of the ApiFailResponse.
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private mixed $message,
        private mixed $errors,
        private int $code = Response::HTTP_BAD_REQUEST,
        private array $metadata = [],
        private array $headers = []
    ) {}

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function toResponse($request): \Illuminate\Http\JsonResponse // @pest-ignore-type
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
