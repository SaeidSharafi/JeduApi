<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

final class UserSelectOptionData extends Data
{
    #[Computed]
    public string $title;

    #[Computed]
    public string $subtitle;

    public function __construct(
        public int $id,
        public string $phone,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $email,
        public ?string $avatar_url = null,
    ) {
        $this->title    = $this->first_name.' '.$this->last_name;
        $this->subtitle = filled($this->email)
            ? $this->email." ({$this->phone})"
            : $this->phone;
    }

    protected function exceptProperties(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
            'phone',
        ];
    }
}
