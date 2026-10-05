<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class UserQueryDefinition
{
    /** @return Builder<User> */
    public function query(): Builder
    {
        $query = QueryBuilder::for(User::class);

        $query
            ->allowedFilters([
                AllowedFilter::callback('name', function (Builder $query, mixed $value): void {
                    $query->whereRaw("concat(first_name, ' ', last_name) like ?", '%'.$value.'%');
                }),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('phone'),
                AllowedFilter::partial('civil_id'),
                AllowedFilter::exact('wallet_status', 'wallet.status'),
                AllowedFilter::exact('civil_id_type'),
                AllowedFilter::callback('date_of_birth_from',
                    function (Builder $query, mixed $value): void {
                        $query->whereJalaiDate('date_of_birth', '>=', $value);
                    },
                ),
                AllowedFilter::callback('date_of_birth_to',
                    function (Builder $query, mixed $value): void {
                        $query->whereJalaiDate('date_of_birth', '<=', $value);
                    },
                ),
            ])
            ->allowedSorts([
                'first_name',
                'last_name',
                'email',
                'phone',
                'civil_id',
                'civil_id_type',
                'date_of_birth',
            ])
            ->with('wallet')
            ->orderBy('users.id');

        return $query->getEloquentBuilder();
    }
}
