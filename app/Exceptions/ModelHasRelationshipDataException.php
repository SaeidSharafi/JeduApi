<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;
use JetBrains\PhpStorm\Pure;
use Throwable;

final class ModelHasRelationshipDataException extends Exception
{
    protected string $relatedModel;

    #[Pure]
    public function __construct(string $relatedModel, string $message = '', ?Throwable $previous = null)
    {
        $this->relatedModel = $relatedModel;
        $message            = $message ?: __(
            'messages.errors.model_has_relationship_data',
            [
                'related_model' => getModelLabel($relatedModel),
            ]
        );
        parent::__construct($message, 0, $previous);
    }

    public function getRelatedModel(): string
    {
        return $this->relatedModel;
    }

    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->validationError($this->getMessage());
    }
}
