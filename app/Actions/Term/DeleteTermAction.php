<?php

declare(strict_types=1);

namespace App\Actions\Term;

use App\Models\Term;

final class DeleteTermAction
{
    public function execute(Term $term): void
    {
        $term->delete();
    }
}
