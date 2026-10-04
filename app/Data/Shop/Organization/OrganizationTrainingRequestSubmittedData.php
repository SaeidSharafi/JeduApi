<?php

declare(strict_types=1);

namespace App\Data\Shop\Organization;

use App\Models\OrganizationTrainingRequest;
use Spatie\LaravelData\Data;

final class OrganizationTrainingRequestSubmittedData extends Data
{
    public function __construct(public string $reference) {}

    public static function fromModel(OrganizationTrainingRequest $request): self
    {
        return new self($request->uuid);
    }
}
