<?php

declare(strict_types=1);

namespace App\Data\Teacher;

use App\Data\MediaData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class TeacherSelectOptionData extends Data
{
    #[Computed]
    public string $title;

    #[Computed]
    public string $subtitle;

    #[Computed]
    public string $image_url;

    public function __construct(
        public int $id,
        public string $first_name,
        public string $last_name,
        public string $email,
        public string $phone,
        #[DataCollectionOf(MediaData::class)]
        public Collection $media,
    ) {
        $this->title     = $this->first_name.' '.$this->last_name;
        $this->subtitle  = $this->email." ({$this->phone})";
        $this->image_url = $this->media?->first()?->url ?: '';

    }

    protected function exceptProperties(): array
    {
        return [
            'first_name',
            'last_name',
            'media',
            'email',
            'phone',
        ];
    }
}
