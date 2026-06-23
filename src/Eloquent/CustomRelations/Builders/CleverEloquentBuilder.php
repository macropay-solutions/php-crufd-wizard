<?php

namespace MacropaySolutions\CrufdWizard\Eloquent\CustomRelations\Builders;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use MacropaySolutions\CrufdWizard\Models\BaseModel;

class CleverEloquentBuilder extends Builder
{
    protected bool $getUnHydrated = false;

    /**
     * @throws \Throwable
     */
    public function getUnHydrated(array|string $columns = ['*']): Collection
    {
        return $this->toBase()->get($columns);
    }

    /**
     * @throws \Throwable
     */
    public function paginateUnHydrated(
        int|null|\Closure $perPage = null,
        array|string $columns = ['*'],
        string $pageName = 'page',
        int|null $page = null,
        int|null|\Closure $total = null,
    ): LengthAwarePaginator {
        $this->getUnHydrated = true;
        $result = $total !== null ?
            $this->paginate($perPage, $columns, $pageName, $page, $total) :
            $this->paginate($perPage, $columns, $pageName, $page);
        $this->getUnHydrated = false;

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function simplePaginateUnHydrated(
        int|null $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        int|null $page = null
    ): Paginator {
        $this->getUnHydrated = true;
        $result = $this->simplePaginate($perPage, $columns, $pageName, $page);
        $this->getUnHydrated = false;

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function cursorPaginateUnHydrated(
        int|null $perPage = null,
        array|string $columns = ['*'],
        string $cursorName = 'cursor',
        Cursor|string|null $cursor = null
    ): CursorPaginator {
        $this->getUnHydrated = true;
        $result = $this->cursorPaginate($perPage, $columns, $cursorName, $cursor);
        $this->getUnHydrated = false;

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function get($columns = ['*']): \Illuminate\Database\Eloquent\Collection|array|Collection
    {
        return $this->getUnHydrated ? $this->getUnHydrated($columns) : parent::get($columns);
    }

    /**
     * If $relations is list array and $callback is Closure, the closure will be applied to all relations from the list
     * @inheritDoc
     */
    public function with($relations, $callback = null): static
    {
        if (!$callback instanceof \Closure) {
            $this->eagerLoad = \array_merge(
                $this->eagerLoad,
                $this->parseWithRelations(\is_string($relations) ? \func_get_args() : $relations)
            );

            return $this;
        }

        $this->eagerLoad = \array_merge($this->eagerLoad, $this->parseWithRelations(
            (\is_array($relations) && \array_values($relations) === $relations) ?
                \array_map(
                    fn(string $relation): \Closure => $callback,
                    \array_flip($relations)
                ) :
                [$relations => $callback]
        ));

        return $this;
    }

    /**
     * @throws \Throwable
     */
    public function createHydrated(array $attributes = []): BaseModel
    {
        $model = $this->create($attributes);

        return $model::query()->useWritePdo()->where($model->getPrimaryKeyFilter())->firstOrFail();
    }
}
