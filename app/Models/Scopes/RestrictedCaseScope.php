<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces restricted-case access AT THE QUERY LAYER.
 *
 * The rule from the client's own compliance documents is specific: for a user
 * without `cases.view_restricted`, "the response must not contain the field at
 * all". Hiding it in the serialiser is not good enough — that is exactly how it
 * ends up in a dev-tools screenshot.
 *
 * So when the viewer lacks the permission this scope rewrites the SELECT to
 * enumerate every column EXCEPT `restricted_reason_encrypted`. The column never
 * leaves the database, and no amount of downstream carelessness can leak it.
 *
 * Note what this scope deliberately does NOT do: it does not filter the rows
 * out. A restricted case stays present and countable, because a case that
 * silently vanishes from a coordinator's list is a different bug — they would
 * chase a matter that appears to have disappeared.
 */
class RestrictedCaseScope implements Scope
{
    public const RESTRICTED_COLUMN = 'restricted_reason_encrypted';

    public function apply(Builder $builder, Model $model): void
    {
        if (static::viewerMaySeeRestricted()) {
            return;
        }

        $columns = static::selectableColumns($model);

        if ($columns !== []) {
            $builder->select($columns);
        }
    }

    public static function viewerMaySeeRestricted(): bool
    {
        $user = Auth::user();

        return $user !== null
            && method_exists($user, 'can')
            && $user->can('cases.view_restricted');
    }

    /**
     * Every column except the restricted one, fully qualified.
     *
     * @return array<int, string>
     */
    protected static function selectableColumns(Model $model): array
    {
        static $cache = [];

        $table = $model->getTable();

        if (! isset($cache[$table])) {
            $cache[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return array_map(
            fn (string $column) => $table.'.'.$column,
            array_values(array_filter(
                $cache[$table],
                fn (string $column) => $column !== self::RESTRICTED_COLUMN,
            )),
        );
    }
}
