<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Helpers\ProvisioningErrorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

abstract class ExternalProvisioningException extends RuntimeException
{
    /** @var array<string, mixed> */
    public array $metaData;

    /**
     * @param  array<string, mixed>|null  $metaData
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, ?array $metaData = [])
    {
        $this->metaData = $metaData ?? [];
        parent::__construct($message, $code, $previous);
    }

    final public function getMoodleErrorCode(): ?string
    {
        return data_get($this->metaData, 'errorcode');
    }

    /**
     * HTTP status this failure maps to when it reaches the API boundary.
     */
    public function status(): int
    {
        return 503;
    }

    /**
     * Upstream context for the response payload, scrubbed and gated on `app.debug`.
     *
     * Owned by the exception so neither the response service nor each controller has
     * to know how a provisioning failure is disclosed.
     *
     * @return array<string, mixed>
     */
    final public function debugContext(): array
    {
        if (! config('app.debug')) {
            return [];
        }

        return ['debug' => ProvisioningErrorContext::sanitize($this->metaData)];
    }

    /**
     * Renders itself as the API error payload.
     *
     * Laravel consults this method before its `renderable()` callbacks, so the mapping
     * lives on the failure instead of in a global closure. Non-JSON requests return
     * null so they keep falling through to the default HTML handler, exactly as the
     * previous `renderable()` hook did.
     */
    final public function render(Request $request): ?JsonResponse
    {
        $isApiRequest = $request->expectsJson()
            || $request->is('api/*')
            || str_starts_with($request->path(), 'api/');

        if (! $isApiRequest) {
            return null;
        }

        return response()->json([
            'message'  => $this->getMessage(),
            'errors'   => $this->debugContext() ?: null,
            'metadata' => [],
        ], $this->status());
    }

    /**
     * Laravel's reporting hook, and the only place this hierarchy is logged.
     *
     * Reporting `false` is what the `dontReport()` entries for the recoverable and
     * unrecoverable classes used to express: the framework skips both its fallback
     * log line and every `reportable` callback (Sentry registers one). Keeping the
     * decision here instead means the same "stay quiet" semantics, with a hook we
     * can build the diagnostic payload in.
     */
    public function report(): bool
    {
        try {
            Log::channel($this->logChannel())->log(
                $this instanceof RecoverableProvisioningException ? 'debug' : 'error',
                'External provisioning failed',
                $this->logContext()
            );
        } catch (Throwable) {
            // Logging is diagnostic and must never replace the failure it describes.
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    final protected function logContext(): array
    {
        $previous = $this->getPrevious();

        return [
            'exception' => static::class,
            'message'   => $this->getMessage(),
            'code'      => $this->getCode(),
            'origin'    => ProvisioningErrorContext::origin(),
            'previous'  => $previous !== null
                ? $previous::class.': '.$previous->getMessage()
                : null,
            'meta' => ProvisioningErrorContext::sanitize($this->metaData),
        ];
    }

    final protected function logChannel(): string
    {
        try {
            return config('logging.channels.provisioning') !== null ? 'provisioning' : 'stack';
        } catch (Throwable) {
            return 'stack';
        }
    }
}
