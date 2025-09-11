<?php

declare(strict_types=1);

namespace App\Data\Transformer;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final class AdvancedDateTimeInterfaceTransformer implements Transformer
{
    private string $format;

    public function __construct(
        ?string $format = null,
        private ?string $setTimeZone = null
    ) {
        [$this->format] = Arr::wrap($format ?? config('data.date_output_format'));
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        $this->setTimeZone ??= config('data.date_timezone');
        /** @var DateTimeInterface $value */
        if ($this->setTimeZone) {
            $value = (clone $value)->setTimezone(new DateTimeZone($this->setTimeZone));
        }

        return $value->format(mb_ltrim($this->format, '!'));
    }
}
