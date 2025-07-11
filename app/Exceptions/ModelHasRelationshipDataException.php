<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use JetBrains\PhpStorm\Pure;

final class ModelHasRelationshipDataException extends Exception
{
    protected string $relatedModel;

    #[Pure]
    public function __construct(string $relatedModel, string $message = '', int $code = 422, ?Throwable $previous = null)
    {
        $this->relatedModel = $relatedModel;
        $message            = $message ?: __(
            'messages.errors.model_has_relationship_data',
            [
                'related_model' => getModelLabel($relatedModel),
            ]
        );
        parent::__construct($message, $code, $previous);
    }

    public function getRelatedModel(): string
    {
        return $this->relatedModel;
    }
}
