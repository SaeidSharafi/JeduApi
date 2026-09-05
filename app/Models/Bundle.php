<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProductableContract;
use App\Enums\Content\PublicationStatusEnum;
use App\Traits\HasCategories;
use App\Traits\HasMedia;
use App\Traits\IsProductable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Mediable\Mediable;

final class Bundle extends Model implements ProductableContract
{
    use HasCategories;
    use HasFactory;
    use HasMedia;
    use IsProductable;
    use Mediable;

    protected $fillable
        = [
            'slug', 'full_name', 'short_name', 'description', 'thumbnail_url', 'properties', 'additional_info', 'faq',
            'status', 'created_by',
        ];

    /** @return MorphToMany<Category, $this> */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', null, 'category_id');
    }

    protected function casts(): array
    {
        return [
            'properties' => 'array', 'additional_info' => 'array', 'faq' => 'array',
            'status'     => PublicationStatusEnum::class,
        ];
    }
}
