<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class UserManagementSearch
{
    public static function apply(Builder $query, string $search, string $numberColumn): Builder
    {
        if ($search === '') return $query;

        $table = $query->getModel()->getTable();
        $mysql = $query->getModel()->getConnection()->getDriverName() === 'mysql';
        $fullName = $mysql
            ? "CONCAT(COALESCE($table.first_name, ''), ' ', COALESCE($table.middle_name, ''), ' ', COALESCE($table.last_name, ''))"
            : "(COALESCE($table.first_name, '') || ' ' || COALESCE($table.middle_name, '') || ' ' || COALESCE($table.last_name, ''))";
        $firstLast = $mysql
            ? "CONCAT(COALESCE($table.first_name, ''), ' ', COALESCE($table.last_name, ''))"
            : "(COALESCE($table.first_name, '') || ' ' || COALESCE($table.last_name, ''))";
        $pattern = '%'.mb_strtolower($search).'%';

        return $query->where(function (Builder $query) use ($table, $numberColumn, $fullName, $firstLast, $pattern) {
            $query->whereRaw("LOWER($fullName) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER($firstLast) LIKE ?", [$pattern])
                ->orWhereRaw("LOWER($table.$numberColumn) LIKE ?", [$pattern])
                ->orWhereHas('user', fn (Builder $user) => $user->whereRaw('LOWER(email) LIKE ?', [$pattern])->orWhereRaw('LOWER(name) LIKE ?', [$pattern]));
        });
    }
}
