<?php

declare(strict_types=1);

namespace App\Providers;

use DateTimeInterface;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

final class BuilderMacroServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Builder::macro('whereJalaiDate', function (
            \Illuminate\Contracts\Database\Query\Expression|string $column,
            DateTimeInterface|string|null $operator,
            Verta|string|null $value = null,
            $boolean = 'and'
        ): Builder {
            $jalaiDate = $value;

            if (is_string($value)) {
                $jalaiDate = Verta::parse($value);
            }

            return $this->whereDate($column, $operator, $jalaiDate?->toCarbon(), $boolean);
        });
    }
}
