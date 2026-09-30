<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ApiResponseInterface;
use Exception;
use Illuminate\Http\Request;
use Throwable;

final class ModelHasRelationshipDataException extends Exception
{
    protected string $relatedModel;

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        string $relatedModel,
        string $message = '',
        ?Throwable $previous = null,
        private readonly ?array $errors = null,
    ) {
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

    /**
     * @return array<string, mixed>|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function render(Request $request): ApiResponseInterface
    {
        return apiResponse()->error($this->getMessage(), 422, $this->getErrors());
    }
}
