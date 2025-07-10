<?php

declare(strict_types=1);

namespace App\Actions\Term;

use App\Data\Admin\Term\CreateTermData;
use App\Models\Term;

final class CreateTermAction
{
    public function execute(CreateTermData $data): Term
    {
        return Term::create($data->toArray());
    }
}
