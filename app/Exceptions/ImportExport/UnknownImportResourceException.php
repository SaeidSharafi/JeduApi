<?php

declare(strict_types=1);

namespace App\Exceptions\ImportExport;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Raised when a requested spreadsheet resource has no registered handler.
 *
 * Resource names are never resolved to class names, so anything outside the
 * fixed registry is simply not found.
 */
final class UnknownImportResourceException extends NotFoundHttpException
{
    public static function forResource(string $resource): self
    {
        return new self("Spreadsheet resource [{$resource}] is not registered.");
    }
}
