<?php

declare(strict_types=1);

namespace App\Scribe\OpenApi;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

final class AddResponseExamples extends OpenApiGenerator
{
    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        foreach ($endpoint->responses->groupBy('status') as $status => $responses) {
            if ($responses->count() < 2 || ! isset($pathItem['responses'][$status]['content']['application/json'])) {
                continue;
            }

            $examples = [];
            foreach ($responses as $response) {
                $value = json_decode($response->content ?? '');
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                $key            = 'scenario-'.(count($examples) + 1);
                $examples[$key] = [
                    'summary' => $response->description ?: 'Response '.(count($examples) + 1),
                    'value'   => $value,
                ];
            }

            if (count($examples) > 1) {
                unset($pathItem['responses'][$status]['content']['application/json']['example']);
                $pathItem['responses'][$status]['content']['application/json']['examples'] = $examples;
            }
        }

        return $pathItem;
    }
}
