<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TermStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\TermFactory;

final class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use HasFactory;

    protected $fillable
        = [
            'name',
            'status',
            'academic_year',
            'start_date',
            'end_date',
        ];

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'status'     => TermStatusEnum::class,
            'start_date' => 'datetime:Y-m-d',
            'end_date'   => 'datetime:Y-m-d',
        ];
    }
}
