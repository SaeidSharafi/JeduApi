<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class DigitalAssetBlockData extends Data
{
    public function __construct(
        #[DataCollectionOf(DigitalAssetFileData::class)]
        public DataCollection $files,
    ) {}
}
