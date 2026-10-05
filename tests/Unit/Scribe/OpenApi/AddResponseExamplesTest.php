<?php

declare(strict_types=1);

use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenAPISpecWriter;

it('exposes every same-status payment scenario as a named OpenAPI example', function (int $status, array $fixtures): void {
    $responses = [];
    foreach ($fixtures as $description => $fixture) {
        $responses[] = ['status' => $status, 'description' => $description, 'content' => file_get_contents(base_path($fixture))];
    }
    $endpoint = new OutputEndpointData([
        'httpMethods' => ['POST'], 'uri' => 'api/v1/shop/checkout',
        'metadata'    => new Metadata(['title' => 'Checkout', 'authenticated' => true]),
        'responses'   => $responses,
    ]);

    $spec = (new OpenAPISpecWriter(new DocumentationConfig(config('scribe'))))
        ->generateSpecContent([['name' => 'Checkout', 'description' => '', 'endpoints' => [$endpoint]]]);
    $content = $spec['paths']['/api/v1/shop/checkout']['post']['responses'][$status]['content']['application/json'];

    expect($content['examples'])->toHaveCount(2);
    foreach (array_values($responses) as $index => $response) {
        expect($content['examples']['scenario-'.($index + 1)]['summary'])->toBe($response['description'])
            ->and($content['examples']['scenario-'.($index + 1)]['value'])->toEqual(json_decode($response['content']));
    }
    expect($content['schema']['oneOf'])->toHaveCount(2);
})->with([
    '201 initiation and completion' => [201, [
        'gateway redirect required'                  => 'resources/responses/shop/checkout/show.json',
        'payment completed without gateway redirect' => 'resources/responses/shop/payment/checkout-payment-successful.json',
    ]],
    '500 paid and free recovery' => [500, [
        'unexpected processing failure'     => 'resources/responses/shop/payment/order-processing-error.json',
        'free order completion rolled back' => 'resources/responses/shop/payment/free-order-processing-error.json',
    ]],
]);

it('preserves response documentation without multiple JSON examples', function (array $responses): void {
    $endpoint = new OutputEndpointData([
        'httpMethods' => ['GET'], 'uri' => 'example',
        'metadata'    => new Metadata(['title' => 'Example']), 'responses' => $responses,
    ]);
    $groups                              = [['name' => 'Examples', 'description' => '', 'endpoints' => [$endpoint]]];
    $baseConfig                          = config('scribe');
    $baseConfig['openapi']['generators'] = [];
    $baseSpec                            = (new OpenAPISpecWriter(new DocumentationConfig($baseConfig)))->generateSpecContent($groups);

    $spec = (new OpenAPISpecWriter(new DocumentationConfig(config('scribe'))))->generateSpecContent($groups);

    expect($spec)->toEqual($baseSpec);
})->with([
    'single JSON response' => [[['status' => 200, 'content' => '{"ok":true}', 'description' => 'Success']]],
    'binary scenarios'     => [[
        ['status' => 200, 'content' => '<<binary>> PDF file', 'description' => 'PDF'],
        ['status' => 200, 'content' => '<<binary>> Image file', 'description' => 'Image'],
    ]],
]);
