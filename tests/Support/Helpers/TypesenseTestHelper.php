<?php

declare(strict_types=1);

namespace Tests\Support\Helpers;

use App\Models\Blog\BlogPost;
use App\Models\Product;
use Exception;

final class TypesenseTestHelper
{
    /**
     * Check if Typesense is available and accessible for testing.
     * Performs a health check with a short timeout.
     */
    public static function setUpTypeSense(): bool
    {
        config()->set('scout.driver', 'typesense');
        // Check if we have the required config
        $host   = config('scout.typesense.client-settings.nearest_node.host');
        $port   = config('scout.typesense.client-settings.nearest_node.port');
        $apiKey = config('scout.typesense.client-settings.api_key');

        if (! $host || ! $port || ! $apiKey) {
            return false;
        }
        // Try to ping Typesense health endpoint
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => "http://{$host}:{$port}/health",
                CURLOPT_RETURNTRANSFER => true, // Return the response instead of outputting it
                CURLOPT_TIMEOUT        => 2,          // Set a short timeout
                CURLOPT_HTTPHEADER     => [
                    'X-TYPESENSE-API-KEY: '.$apiKey,
                ],
            ]);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                return false;
            }

            $data = json_decode((string) $response, true);

            return isset($data['ok']) && $data['ok'] === true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Skip test if Typesense is not available.
     * Returns true if test should be skipped.
     */
    public static function skipIfTypesenseUnavailable(): void
    {
        if (! self::setUpTypeSense()) {
            test()->markTestSkipped('Typesense is not available');
        }
    }

    public static function regenerateIndex(): void
    {
        Product::query()->searchable();
        BlogPost::query()->searchable();
    }
}
