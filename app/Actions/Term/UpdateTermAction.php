<?php

declare(strict_types=1);

namespace App\Actions\Term;

use App\Data\Admin\Term\CreateTermData;
use App\Models\Term;

final class UpdateTermAction
{
    public function execute(Term $term, CreateTermData $data): Term
    {
        $term->update($data->toArray());

        return $term;
    }
}
