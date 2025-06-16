<?php

namespace App\Data\Teacher;

use App\Data\MediaData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\AutoLazy;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Exclude;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class TeacherSelectOptionData extends Data
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
    )
    {
        $this->title = $this->first_name . ' ' . $this->last_name;
        $this->subtitle = $this->email .  " ({$this->phone})";
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
