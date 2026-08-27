<?php

declare(strict_types=1);

namespace App\Data\Admin\ContactRequest;

use Spatie\LaravelData\Data;

final class ContactRequestNoteData extends Data
{
    public function __construct(public ?string $note) {}

    public static function rules(): array
    {
        return ['note' => ['present', 'nullable', 'string', 'max:1000']];
    }
}
