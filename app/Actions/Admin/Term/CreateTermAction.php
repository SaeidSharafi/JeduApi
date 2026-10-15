<?php

declare(strict_types=1);

namespace App\Actions\Admin\Term;

use App\Data\Admin\Term\CreateTermData;
use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Models\Term;

final class CreateTermAction
{
    public function execute(CreateTermData $data): Term
    {
        $term = Term::create($data->toArray());

        StorefrontCacheInvalidated::dispatch(...StorefrontTag::catalogWith(StorefrontTag::HomeBlocks));

        return $term;
    }
}
