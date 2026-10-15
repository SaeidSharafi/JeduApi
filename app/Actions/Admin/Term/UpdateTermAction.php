<?php

declare(strict_types=1);

namespace App\Actions\Admin\Term;

use App\Data\Admin\Term\CreateTermData;
use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Models\Term;

final class UpdateTermAction
{
    public function execute(Term $term, CreateTermData $data): Term
    {
        $term->update($data->toArray());

        StorefrontCacheInvalidated::dispatch(...StorefrontTag::catalogWith(StorefrontTag::HomeBlocks));

        return $term;
    }
}
