<?php

declare(strict_types=1);

namespace App\Data\ImportExport;

/**
 * One spreadsheet column of an import/export resource contract.
 *
 * The heading is resolved through a translation key so the generated template
 * follows the requested locale, while {@see self::aliases()} stays locale
 * independent: a file with English or Persian headings must always be accepted.
 */
final readonly class SpreadsheetColumnDefinition
{
    /**
     * @param  list<string>  $aliases  Accepted heading variants, English and Persian.
     */
    public function __construct(
        public string $key,
        public string $headingKey,
        public array $aliases,
        public bool $required,
        public ?string $example,
        public string $guidanceKey,
    ) {}

    public function heading(): string
    {
        return (string) __($this->headingKey);
    }

    public function guidance(): string
    {
        return (string) __($this->guidanceKey);
    }

    /**
     * Every heading variant that resolves to this column.
     *
     * The localized heading is always accepted so the generated template
     * round-trips, whatever locale produced it.
     *
     * @return list<string>
     */
    public function headings(): array
    {
        return [$this->key, $this->heading(), ...$this->aliases];
    }
}
