<?php

namespace App\Http\Responses;

use App\Contracts\ApiResponseInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class ApiErrorResponse implements ApiResponseInterface
{
    public function __construct(
        private string $message,
        private ?\Throwable $exception = null,
        private int $code = Response::HTTP_INTERNAL_SERVER_ERROR,
        private array $headers = []
    ) {}

    /**
     * {@inheritDoc}
     *
     */
    public function toResponse($request): \Illuminate\Http\JsonResponse //@pest-ignore-type
    {
        $response = ['message' => $this->message];

        if (! is_null($this->exception) && config('app.debug')) {
            $response['debug'] = [
                'message' => $this->exception->getMessage(),
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine(),
                'trace' => $this->exception->getTraceAsString(),
            ];
        }

        return response()->json($response, $this->code, $this->headers);
    }
}
