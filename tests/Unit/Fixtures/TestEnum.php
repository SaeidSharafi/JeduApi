<?php

declare(strict_types=1);

namespace Tests\Unit\Fixtures;

use App\Traits\AdvanceEnum;

enum TestEnum: string {
    use AdvanceEnum;
    case FOO = 'foo';
    case BAR = 'bar';
}
