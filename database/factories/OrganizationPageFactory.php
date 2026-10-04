<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrganizationPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationPage>
 */
final class OrganizationPageFactory extends Factory
{
    protected $model = OrganizationPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'singleton_key'               => OrganizationPage::SINGLETON_KEY,
            'vendor_id'                   => null,
            'hero_title'                  => null,
            'hero_description'            => null,
            'ims_portal_url'              => null,
            'request_section_title'       => null,
            'request_section_explanation' => null,
            'faqs'                        => [],
            'hero_image_url'              => null,
            'educational_calendar_url'    => null,
        ];
    }
}
