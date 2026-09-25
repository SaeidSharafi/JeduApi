<?php

declare(strict_types=1);

namespace App\Contracts\ImportExport;

use App\Data\ImportExport\ImportRowResult;
use App\Data\ImportExport\SpreadsheetColumnDefinition;
use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Enums\ProvisioningProviderEnum;

/**
 * Resource owned contract consumed by the central import engine.
 *
 * The engine only understands resource names, column definitions and row
 * results; every resource specific field, rule and provider flag stays here.
 */
interface ImportResourceContract
{
    public function resource(): SpreadsheetResourceEnum;

    /**
     * @return list<SpreadsheetColumnDefinition>
     */
    public function columns(): array;

    /**
     * Example row rendered in the generated template, keyed by column key.
     *
     * @return array<string, string|null>
     */
    public function exampleRow(): array;

    /**
     * Providers this resource can request additive user provisioning for.
     *
     * @return list<ProvisioningProviderEnum>
     */
    public function providerCapabilities(): array;

    /**
     * Validate and normalize one spreadsheet row.
     *
     * @param  array<string, string|null>  $values  Cell values keyed by column key.
     * @param  ImportIdentityKeyEnum  $identityKey  Identity the row is matched on.
     */
    public function validateRow(array $values, ImportIdentityKeyEnum $identityKey): ImportRowResult;

    /**
     * Persist one previously validated row inside the engine's transaction.
     */
    public function importRow(ImportRowResult $row): void;
}
