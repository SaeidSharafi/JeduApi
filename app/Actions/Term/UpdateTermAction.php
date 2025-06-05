<?php

declare(strict_types=1);

namespace App\Actions\Term;

use App\Data\Term\CreateTermData;
use App\Data\Term\UpdateTermData;
use App\Models\Term;

class UpdateTermAction
{
    public function execute(Term $term, CreateTermData $data): Term
    {
        $term->update($data->toArray());
        return $term;
    }
}
