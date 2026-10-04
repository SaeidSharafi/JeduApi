<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasMedia;
use Database\Factories\OrganizationPageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Mediable\Mediable;

final class OrganizationPage extends Model
{
    /** @use HasFactory<OrganizationPageFactory> */
    use HasFactory;

    use HasMedia;
    use Mediable;

    public const int SINGLETON_KEY = 1;

    protected $attributes = [
        'singleton_key' => self::SINGLETON_KEY,
    ];

    protected $fillable = [
        'vendor_id',
        'hero_title',
        'hero_description',
        'ims_portal_url',
        'request_section_title',
        'request_section_explanation',
        'faqs',
        'hero_image_url',
        'educational_calendar_url',
    ];

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function singleton(Builder $query): Builder
    {
        return $query->where('singleton_key', self::SINGLETON_KEY);
    }

    protected function casts(): array
    {
        return [
            'singleton_key' => 'integer',
            'vendor_id'     => 'integer',
            'faqs'          => 'array',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }
}
