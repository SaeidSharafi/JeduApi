<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Enums\Product\ProductableEnum;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListStudentEnrollmentsAction
{
    /**
     * Paginate the given customer's enrollments of a single productable type.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function handle(
        User $user,
        ProductableEnum $productableType,
        array $filters,
        int $perPage,
    ): LengthAwarePaginator {
        return $user->enrollments()
            ->withWhereHas(
                'productDeliveryOption', function ($query) use ($filters, $productableType): void {
                    $query->when(data_get($filters, 'fulfillment_type'), function ($query, $fulfillmentType): void {
                        $query->where('fulfillment_type', $fulfillmentType);
                    })->withWhereHas(
                        'product', function ($query) use ($filters, $productableType): void {
                            $query
                                ->where('productable_type', $productableType->value)
                                ->when(data_get($filters, 'name'), function ($query, $name): void {
                                    $query->whereLike('name', "%{$name}%");
                                })
                                ->with(['productableWithAllRelations']);
                        })->with('teachers.media');
                }
            )
            ->with(['orderItem.vendor'])
            ->paginate($perPage)
            ->withQueryString();
    }
}
