<?php

declare(strict_types=1);

namespace App\Data\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Casts\IterableItemCast;
use Spatie\LaravelData\Casts\Uncastable;
use Spatie\LaravelData\Exceptions\CannotCastDate;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

use function verta;

final class AdvancedDateTimeInterfaceCast implements Cast, IterableItemCast
{
    public function __construct(
        private null|string|array $format = null,
        private ?string $type = null,
        private ?string $setTimeZone = null,
        private ?string $timeZone = null
    ) {}

    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): DateTimeInterface|Uncastable {
        return $this->castValue($this->type ?? $property->type->type->findAcceptedTypeForBaseType(DateTimeInterface::class), $value);
    }

    public function castIterableItem(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): DateTimeInterface|Uncastable {
        return $this->castValue($property->type->iterableItemType, $value);
    }

    private function castValue(
        ?string $type,
        mixed $value,
    ): Uncastable|null|DateTimeInterface {

        $formats = collect($this->format ?? config('data.date_format'));
        if ($type === null) {
            return Uncastable::create();
        }

        // Truncate nanoseconds to microseconds (first 6 digits)
        if (is_string($value)) {
            $value = preg_replace('/\.(\d{6})\d*Z$/', '.$1Z', $value);
        }
        $this->setTimeZone ??= config('data.date_timezone');

        if ($type === Verta::class) {
            $value = rescue(fn (): \Carbon\Carbon => Carbon::parse($value), report: false);
            $verta = verta($value, isset($this->timeZone) ? new DateTimeZone($this->timeZone) : null);

            if ($this->setTimeZone) {
                return $verta->setTimezone(new DateTimeZone($this->setTimeZone));
            }
        }

        /** @var DateTimeInterface|null $datetime */
        $datetime = $formats
            ->map(fn (string $format) => rescue(fn () => $type::createFromFormat(
                $format,
                $value instanceof DateTimeInterface ? $value->format($format) : (string) $value,
                isset($this->timeZone) ? new DateTimeZone($this->timeZone) : null
            ), report: false))
            ->first(fn ($value): bool => (bool) $value);

        if (! $datetime) {
            throw CannotCastDate::create($formats->toArray(), $type, $value);
        }

        $this->setTimeZone ??= config('data.date_timezone');

        if ($this->setTimeZone) {
            return $datetime->setTimezone(new DateTimeZone($this->setTimeZone));
        }

        return $datetime;
    }
}
