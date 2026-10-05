<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Resources;

use App\Contracts\ImportExport\ExportResourceContract;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Models\User;
use App\Services\ImportExport\UserQueryDefinition;
use BackedEnum;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final readonly class UserExportResource implements ExportResourceContract
{
    /** @var list<string> */
    private const array COLUMNS = [
        'id', 'first_name', 'last_name', 'phone', 'email', 'phone2', 'civil_id',
        'civil_id_type', 'date_of_birth', 'father_name', 'gender', 'education_level',
        'field_of_study', 'education_status', 'created_at', 'updated_at',
    ];

    public function __construct(private UserQueryDefinition $users) {}

    public function resource(): SpreadsheetResourceEnum
    {
        return SpreadsheetResourceEnum::USERS;
    }

    public function authorize(): void
    {
        Gate::authorize('export', User::class);
    }

    /** @return Builder<User> */
    public function query(): Builder
    {
        return $this->users->query()->select(self::COLUMNS);
    }

    public function headings(): array
    {
        return self::COLUMNS;
    }

    public function map(Model $row, string $locale): array
    {
        return array_map(static function (string $column) use ($row, $locale): string|int|null {
            $value = $row->getAttribute($column);

            if ($value instanceof BackedEnum) {
                return (string) __('enums.'.class_basename($value).".{$value->value}", [], $locale);
            }

            if ($value !== null && in_array($column, ['date_of_birth', 'created_at', 'updated_at'], true)) {
                return Verta::instance($value)->format($column === 'date_of_birth' ? 'Y-m-d' : 'Y-m-d H:i:s');
            }

            return $value;
        }, self::COLUMNS);
    }
}
