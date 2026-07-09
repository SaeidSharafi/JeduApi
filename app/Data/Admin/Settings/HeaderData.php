<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class HeaderData extends Data
{
    public function __construct(
        public ?MediaData $logo,
        public string $contact_phone,
        public string $contact_email
    ) {}

    public static function getDefaults(): array
    {
        return [
            'logo'             => null,
            'logo_url'         => null,
            'contact_phone' => '+98-21-12345678',
            'contact_email' => 'info@jedu.ir',
        ];
    }
}
