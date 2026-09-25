<?php

declare(strict_types=1);

namespace App\Data\ImportExport;

use App\Enums\ImportExport\ImportRowActionEnum;

/**
 * Outcome of validating a single resource row, owned by the resource contract.
 *
 * Errors keep the shape persisted in `import_run_rows.errors` and returned to
 * the client, so nothing has to re-map them.
 */
final readonly class ImportRowResult
{
    /**
     * @param  list<array{field: string, code: string, message: string}>  $errors
     * @param  array<string, mixed>  $data  Normalized resource values exposed under rows[].data.
     * @param  array<string, mixed>  $sensitiveData  Values used during approval but never persisted or returned.
     */
    public function __construct(
        public bool $valid,
        public ?ImportRowActionEnum $action,
        public ?string $identity,
        public array $data,
        public array $errors,
        public ?string $targetResourceId = null,
        public array $sensitiveData = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(string $identity, array $data, array $sensitiveData = []): self
    {
        return new self(true, ImportRowActionEnum::CREATE, $identity, $data, [], null, $sensitiveData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function update(
        string $identity,
        array $data,
        ?string $targetResourceId = null,
        array $sensitiveData = [],
    ): self {
        return new self(true, ImportRowActionEnum::UPDATE, $identity, $data, [], $targetResourceId, $sensitiveData);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{field: string, code: string, message: string}>  $errors
     */
    public static function invalid(?string $identity, array $data, array $errors): self
    {
        return new self(false, null, $identity, $data, $errors);
    }

    /**
     * Rejects the row because its identity is duplicated in the same file,
     * keeping any field error already found on the row.
     */
    public function withDuplicateIdentityError(string $field, string $message): self
    {
        return new self(
            false,
            null,
            $this->identity,
            $this->data,
            [['field' => $field, 'code' => 'duplicate_identity', 'message' => $message], ...$this->errors],
            $this->targetResourceId,
            $this->sensitiveData,
        );
    }
}
