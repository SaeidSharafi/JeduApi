<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

final readonly class GenerateEnrollmentSurveyUrlAction
{
    public function __construct(private EnrollmentSurveyPolicyAction $surveyPolicy) {}

    /**
     * Return the exact PDO survey URL populated with stable enrollment reporting values.
     */
    public function handle(Enrollment $enrollment): ?string
    {
        $enrollment->loadMissing('productDeliveryOption.product.productable');
        $deliveryOption = $enrollment->productDeliveryOption;
        $product        = $deliveryOption?->product;
        $productable    = $product?->productable;

        if (! $product || ! $this->surveyPolicy->supportsSurvey($enrollment) || ! $productable || blank($deliveryOption->survey_url)) {
            return null;
        }

        if (! $this->surveyPolicy->allowsSurveyCompletion($enrollment)) {
            return null;
        }

        $token = DB::transaction(function () use ($enrollment): string {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());

            if (filled($locked->survey_token) && filled($locked->survey_token_digest)) {
                return $locked->survey_token;
            }

            $token = bin2hex(random_bytes(32));
            $locked->forceFill([
                'survey_token'        => $token,
                'survey_token_digest' => hash('sha256', $token),
            ])->saveQuietly();

            return $token;
        });

        return $this->appendVariables($deliveryOption->survey_url, [
            'enrollment_token' => $token,
            'product_slug'     => (string) $product->slug,
            'product_name'     => (string) $product->name,
            'course_name'      => (string) $productable->full_name,
            'product_type'     => $product->productable_type,
        ]);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function appendVariables(string $url, array $variables): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $base = $parts['scheme'].'://';
        if (isset($parts['user'])) {
            $base .= $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@';
        }
        $base .= $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');

        $ownedNames = array_fill_keys(array_keys($variables), true);
        $queryPairs = [];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            if ($pair === '') {
                continue;
            }
            $name = urldecode(explode('=', $pair, 2)[0]);
            if (! isset($ownedNames[$name])) {
                $queryPairs[] = $pair;
            }
        }
        foreach ($variables as $name => $value) {
            $queryPairs[] = rawurlencode($name).'='.rawurlencode($value);
        }

        return $base.($queryPairs === [] ? '' : '?'.implode('&', $queryPairs))
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
