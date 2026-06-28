<?php

namespace MacropaySolutions\CrufdWizard\Services;

use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\CrufdWizard\Helpers\GeneralHelper;
use MacropaySolutions\CrufdWizard\Models\BaseModel;

abstract class BaseResourceService implements ResourceServiceInterface
{
    public const COUNT_ALIAS_POSTFIX = '_count';
    public const EXIST_ALIAS_POSTFIX = '_exist';
    protected BaseModel $model;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->setBaseModel();
    }

    public function getResourceName(): string
    {
        return $this->model::resourceName();
    }

    /**
     * @inheritDoc
     */
    public function delete(string $identifier): bool
    {
        return (bool)$this->get($identifier, appendIndex: false)->deleteOrFail();
    }

    /**
     * @inheritDoc
     */
    public function get(string $identifier, array $withRelations = [], bool $appendIndex = true): BaseModel
    {
        return $this->model::query()->with($withRelations)->where(
            $this->extractIdentifierConditions($identifier)
        )->firstOrFail()->appendIndexRequiredOnFilteringAttribute($appendIndex);
    }

    /**
     * @inheritDoc
     */
    public function create(array $request): BaseModel
    {
        return $this->model::query()->createHydrated($request);
    }

    /**
     * @inheritDoc
     */
    public function update(string $identifier, array $request): BaseModel
    {
        ($model = $this->get($identifier, appendIndex: false))->update($request);

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function list(array $request): Builder
    {
        $builder = $this->model::query();
        $filters = GeneralHelper::filterDataByKeys($request, $possibleSortColumns = $this->model->getColumns());

        if ($this->model->isIndexRequiredOnFiltering()) {
            $possibleSortColumns = $this->model->getIndexedColumns();

            if (
                $filters !== []
                && [] === \array_filter(
                    GeneralHelper::filterDataByKeys($filters, $possibleSortColumns),
                    fn(mixed $filter): bool => !\is_array($filter)
                )
            ) {
                throw new \Exception('Ignoring filters');
            }
        }

        foreach ($filters as $column => $value) {
            if (!\is_array($value)) {
                $builder->where($column, '=', $value);

                continue;
            }

            if (\is_scalar($value['from'] ?? null)) {
                $builder->where($column, '>=', $value['from']);
            }

            if (\is_scalar($value['to'] ?? null)) {
                $builder->where($column, '<=', $value['to']);
            }
        }

        if (\is_array($request['withRelations'] ?? null)) {
            $builder->with($this->getValidRelations($request['withRelations']));
        }

        if (\is_array($request['withRelationsCount'] ?? null)) {
            $validRelations = $this->getValidRelations($request['withRelationsCount']);
            $builder->withCount(\array_map(
                fn(string $relationName): string => $relationName . ' as ' . $relationName .
                    static::COUNT_ALIAS_POSTFIX,
                $validRelations
            ));
            $possibleSortColumns = \array_merge($possibleSortColumns, \array_map(
                fn(string $relationName): string => $relationName . static::COUNT_ALIAS_POSTFIX,
                $validRelations
            ));
        }

        if (\is_array($request['withRelationsExistence'] ?? null)) {
            $validRelations = $this->getValidRelations($request['withRelationsExistence']);
            $builder->withExists(\array_map(
                fn(string $relationName): string => $relationName . ' as ' . $relationName .
                    static::EXIST_ALIAS_POSTFIX,
                $validRelations
            ));
            $possibleSortColumns = \array_merge($possibleSortColumns, \array_map(
                fn(string $relationName): string => $relationName . static::EXIST_ALIAS_POSTFIX,
                $validRelations
            ));
        }

        if ($this->shouldIgnoreSort($possibleSortColumns, $request)) {
            return $builder;
        }

        foreach ((array)($request['sort'] ?? []) as $orderBy) {
            if (\in_array($orderBy['by'] ?? '', $possibleSortColumns, true)) {
                $builder->orderBy(
                    $orderBy['by'],
                    \strtoupper($orderBy['dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC'
                );
            }
        }

        return $builder;
    }

    /**
     * @throws \Exception
     */
    public function isUpdateOrCreateAble(array $requestBody = []): bool
    {
        if ($this->model->incrementing) {
            return false;
        }

        foreach ($this->model->getPrimaryKeyFilter() as $column => $value) {
            if (!\is_array($value) && \is_string($column)) {
                if (!\array_key_exists($column, $requestBody)) {
                    return false;
                }

                continue;
            }

            if (!\array_key_exists(\reset($value), $requestBody)) {
                return false;
            }
        }

        return true;
    }

    public function getTableName(): string
    {
        return $this->model->getTable();
    }

    public function getIndexRequiredOnFiltering(): array
    {
        return $this->model->getIndexRequiredOnFilteringAttribute();
    }

    public function getModelColumns(bool $includingPrimaryKeyProperty = false): array
    {
        return $this->model->getColumns($includingPrimaryKeyProperty);
    }

    public function getIgnoreExternalUpdateFor(): array
    {
        return $this->model->getIgnoreUpdateFor();
    }

    public function getIgnoreExternalCreateFor(): array
    {
        return $this->model->getIgnoreExternalCreateFor();
    }

    /**
     * @throws \Exception
     */
    abstract protected function setBaseModel(): void;

    /**
     * @throws \Throwable
     */
    protected function addRelationsToExistingModel(array $withRelations, BaseModel $model): void
    {
        foreach ($withRelations as $relationName) {
            $model->{$relationName};
        }
    }

    /**
     *         $exploded = \explode($this->model::COMPOSITE_PK_SEPARATOR, $identifier);
     *
     *         return [
     *             ['col1', \reset($exploded)],
     *             ['col2', \next($exploded)],
     *             ...
     *         ];
     * @throws \Exception
     */
    protected function extractIdentifierConditions(string $identifier): array
    {
        if ($this->model->getKeyName() === '') {
            throw new \Exception('Development error. extractIdentifierConditions function not defined for this model.');
        }

        return [
            [$this->model->getKeyName(), $identifier]
        ];
    }

    protected function shouldIgnoreSort(array $possibleSortColumns, array $request): bool
    {
        foreach ((array)($request['sort'] ?? []) as $sort) {
            if (isset($sort['by'])) {
                return !\in_array($sort['by'], $possibleSortColumns, true);
            }
        }

        return true;
    }

    protected function getValidRelations(array $relations): array
    {
        return \array_values(\array_intersect($this->model::WITH_RELATIONS, $relations));
    }
}
