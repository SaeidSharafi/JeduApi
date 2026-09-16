<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport\ImportResourceContract;
use App\Data\ImportExport\SpreadsheetColumnDefinition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the official import template from the very column contract used by
 * heading validation and row mapping, so template and engine cannot drift.
 *
 * Cell comments carry the field guidance, keeping the sheet limited to one
 * heading row and one example row.
 */
final class ImportTemplateExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings
{
    public function __construct(private readonly ImportResourceContract $resource) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_map(
            static fn (SpreadsheetColumnDefinition $column): string => $column->heading(),
            $this->resource->columns(),
        );
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public function array(): array
    {
        $example = $this->resource->exampleRow();

        return [
            array_map(
                static fn (SpreadsheetColumnDefinition $column): ?string => $example[$column->key] ?? null,
                $this->resource->columns(),
            ),
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $worksheet = $event->getSheet()->getDelegate();

                foreach ($this->resource->columns() as $index => $column) {
                    $coordinate = Coordinate::stringFromColumnIndex($index + 1).'1';

                    $this->addComment($worksheet, $coordinate, $this->headingComment($column));
                }

                if ($this->resource->columns() !== [] && count($this->array()) > 0) {
                    $this->addComment(
                        $worksheet,
                        'A2',
                        (string) __('imports.template.example_notice'),
                    );
                }
            },
        ];
    }

    private function headingComment(SpreadsheetColumnDefinition $column): string
    {
        $lines = [$column->guidance()];

        if ($column->example !== null) {
            $lines[] = (string) __('imports.template.example', ['example' => $column->example]);
        }

        $lines[] = (string) __('imports.template.english_key', ['key' => $column->key]);

        if ($column->required) {
            $lines[] = (string) __('imports.template.required');
        }

        return implode("\n", $lines);
    }

    private function addComment(Worksheet $worksheet, string $coordinate, string $text): void
    {
        $richText = new RichText();
        $richText->createText($text);

        $worksheet->getComment($coordinate)->setText($richText);
    }
}
