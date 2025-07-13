<?php

declare(strict_types=1);

namespace App\Actions\Admin\Term;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Product;
use App\Models\Term;

final class DeleteTermAction
{
    public function execute(Term $term): void
    {
        if ($term->products()->exists()) {
            throw new ModelHasRelationshipDataException(Product::class);
        }
        $term->delete();
    }
}
