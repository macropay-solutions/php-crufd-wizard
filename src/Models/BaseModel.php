<?php

namespace MacropaySolutions\CrufdWizard\Models;

use Carbon\Carbon;
use MacropaySolutions\CrufdWizard\Helpers\GeneralHelper;
use MacropaySolutions\CrufdWizard\Models\Attributes\BaseModelAttributes;
use MacropaySolutions\CrufdWizard\Models\Attributes\BaseModelFrozenAttributes;
use MacropaySolutions\CrufdWizard\Models\Attributes\BaseModelLazyAttributes;
use MacropaySolutions\CrufdWizard\Models\Attributes\BaseModelLazyRelations;
use MacropaySolutions\CrufdWizard\Models\Attributes\BaseModelRelations;
use MacropaySolutions\CrufdWizard\Obvious\CustomRelations\Builders\CleverObviousBuilder;
use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\Str;

/**
 * @property-read ?BaseModelAttributes a manages attributes only. DO NOT STORE THIS IN EXTERNAL VARIABLES!
 * @property-read ?BaseModelRelations r manages relations only. DO NOT STORE THIS IN EXTERNAL VARIABLES!
 */
abstract class BaseModel extends Model
{
    public const RESOURCE_NAME = null;
    public const WITH_RELATIONS = [];
    public const CREATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const UPDATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const COMPOSITE_PK_SEPARATOR = '_';
    /**
     * Setting this to true will not append the primary_key_identifier on response
     * Leave it false if you use casts or any logic that alters (conditionally or not) the attributes of the model
     */
    public const LIST_UN_HYDRATED_WHEN_POSSIBLE = false;
    public $timestamps = false;
    public static $snakeAttributes = false;
    public static ?string $baseModelAttributesFqn = null;
    public static ?string $baseModelRelationsFqn = null;

    /**
     * Cache for a (manages attributes/columns)
     */
    private ?BaseModelAttributes $A = null;

    /**
     * Cache for r (manages relations)
     */
    private ?BaseModelRelations $R = null;

    protected bool $returnNullOnInvalidColumnAttributeAccess = true;
    protected array $ignoreUpdateFor = [];
    protected array $ignoreExternalCreateFor = [];
    protected array $allowNonExternalUpdatesFor = [];
    protected bool $indexRequiredOnFiltering = true;
    protected $hidden = [
        'framework_through_key'
    ];

    private array $incrementsToRefresh = [];

    /**
     * @inheritdoc
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->appends[] = 'primary_key_identifier';
    }

    public static function resourceName(): string
    {
        return static::RESOURCE_NAME ?? Str::snake(Str::pluralStudly(\class_basename(static::class)), '-');
    }

    /**
     * @inheritDoc
     */
    public function newObviousBuilder($query): Builder
    {
        return new CleverObviousBuilder($query);
    }

    public function getColumns(bool $includingPrimary = true): array
    {
        static $uniqueColumns = [];
        // Scope the cache to the specific model class!
        $cacheKey = static::class . ($includingPrimary ? '_1' : '_0');

        if (isset($uniqueColumns[$cacheKey])) {
            return $uniqueColumns[$cacheKey];
        }

        $columns = $includingPrimary ?
            /** $this->primaryKey can be null or empty string so, it must not be included */
            \array_merge(\array_filter([$this->primaryKey]), $this->fillable) :
            /** $this->primaryKey may be in the fillable so, it must be included */
            \array_diff($this->fillable, \array_diff([$this->primaryKey], $this->fillable));

        if (
            !\app()->environment('production')
            && [] !== ($reservedUsed = \array_intersect(
                ['page', 'limit', 'cursor', 'simplePaginate', 'sort', 'sqlDebug', 'logError'],
                $columns
            ))
        ) {
            \app('log')->warning('CrufdWizard warning: the resource ' . $this::resourceName() .
                ' uses reserved words as columns: ' . \implode(',', $reservedUsed));
        }

        return $uniqueColumns[$cacheKey] = \array_unique($columns);
    }

    /**
     * @inheritdoc
     */
    protected function segregatedAccessorsMap(): array
    {
        return [
            'index_required_on_filtering' => fn(): array => $this->getIndexRequiredOnFilteringAttribute(),
            'primary_key_identifier' => fn(): mixed => $this->getPrimaryKeyIdentifierAttribute(),
        ];
    }

    public function getIndexRequiredOnFilteringAttribute(): array
    {
        return $this->indexRequiredOnFiltering ? $this->retrieveFirstSeqIndexedColumns() : [];
    }

    public function getPrimaryKeyIdentifierAttribute(): mixed
    {
        return $this->getKeyName() !== '' ?
            $this->getAttributeValue($this->getKeyName()) :
            \implode($this::COMPOSITE_PK_SEPARATOR, \array_map(
                fn(mixed $value): mixed => (\is_array($value) ? \last($value) : $value),
                $this->getPrimaryKeyFilter()
            ));
    }

    public function appendIndexRequiredOnFilteringAttribute(bool $appendIndex = true): static
    {
        return $appendIndex && $this->indexRequiredOnFiltering ? $this->append(['index_required_on_filtering']) : $this;
    }

    public function getIgnoreUpdateFor(): array
    {
        return $this->ignoreUpdateFor;
    }

    public function getIgnoreExternalCreateFor(): array
    {
        return (string)$this->getKeyName() === '' ?
            $this->ignoreExternalCreateFor : \array_merge(
                $this->ignoreExternalCreateFor,
                \array_diff([$this->getKeyName()], $this->getFillable())
            );
    }

    /**
     * @inheritDoc
     */
    public function setAttribute(mixed $key, mixed $value): mixed
    {
        if (!$this->exists) {
            return parent::setAttribute($key, $value);
        }

        if (\in_array($key, \array_diff($this->ignoreUpdateFor, $this->allowNonExternalUpdatesFor), true)) {
            \app('log')->error(
                'Development bug. Tried to update an ignored column ' . $key . ' on ' . \get_class($this) .
                ' with value: "' . $value . '" on ' . $this->getKeyName() . ' = ' . $this->getKey(
                ) . '. BACKTRACE: ' .
                \json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))
            );

            return $this;
        }

        if (isset($this->incrementsToRefresh[$key])) {
            unset($this->incrementsToRefresh[$key]);
        }

        return parent::setAttribute($key, $value);
    }

    public static function boot(): void
    {
        parent::boot();
        static::creating(function (BaseModel $baseModel): void {
            $baseModel->setCreatedAt(Carbon::now()->format($baseModel::CREATED_AT_FORMAT));
        });

        static::updating(function (BaseModel $baseModel): void {
            $updatedAtColumn = $baseModel->getUpdatedAtColumn();

            if ('' === $baseModel->getAttribute($updatedAtColumn)) {
                $baseModel->setUpdatedAt($baseModel->getOriginal($updatedAtColumn));

                return;
            }

            $baseModel->setUpdatedAt(Carbon::now()->format($baseModel::UPDATED_AT_FORMAT));
        });
    }

    /**
     * @throws \Exception
     */
    public function getPrimaryKeyFilter(): array
    {
        if ($this->getKeyName() === '') {
            throw new \Exception('Development error. getPrimaryKeyFilter function not defined for this model.');
        }

        return [
            [$this->getKeyName(), $this->getAttributeValue($this->getKeyName())],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getKey()
    {
        return $this->getPrimaryKeyIdentifierAttribute();
    }

    /**
     * Overwrite this for sqlite and sqlsrv db drivers if needed
     * @throws \Throwable
     */
    public function retrieveIndexesFromTable(): Collection
    {
        if (!\in_array($driver = $this->getConnection()->getDriverName(), ['mariadb', 'mysql', 'pgsql'], true)) {
            throw new \Exception('Unsupported database driver ' . $driver . ' for retrieving indexes.');
        }

        static $result;
        $callback =
            /**
             * @throws \Throwable
             */
            function () use ($driver): array {
                $tableName = $this->getConnection()->getTablePrefix() . $this->getTable();
                return $this->getConnection()->select([
                    'mariadb' => 'SHOW INDEX FROM ' . $tableName,
                    'mysql' => 'SHOW INDEX FROM ' . $tableName,
                    'pgsql' => "SELECT
                            array_position(ix.indkey, a.attnum) + 1 as Seq_in_index,
                            i.relname as Key_name,
                            a.attname as Column_names
                        from
                            pg_class t,
                            pg_class i,
                            pg_index ix,
                            pg_attribute a
                        where
                            t.oid = ix.indrelid
                            and i.oid = ix.indexrelid
                            and a.attrelid = t.oid
                            and a.attnum = any(ix.indkey)
                            and t.relkind = 'r'
                            and t.relname = '" . $tableName . "'",
                ][$driver]);
            };

        try {
            return $result[$this->getConnectionName() . $this->getTable()] ??= \collect(\app('cache.store')->remember(
                $this::resourceName() . 'IndexesForFiltering',
                Carbon::now()->addDay(),
                $callback
            ));
        } catch (\Throwable) {
            return \collect($callback());
        }
    }

    public function retrieveFirstSeqIndexedColumns(): array
    {
        static $indexes;

        try {
            $result = $indexes[$this->getConnectionName() . $this->getTable()] ??= \array_values(\array_unique(
                $this->retrieveIndexesFromTable()->where('Seq_in_index', 1)->pluck('Column_name')->all()
            ));
        } catch (\Throwable $e) {
            if (GeneralHelper::isDebug()) {
                \app('log')->error($this::class . ' error getting indexes: ' . $e->getMessage());
            }
        }

        if (($result ??= []) === [] && $this->indexRequiredOnFiltering) {
            $this->indexRequiredOnFiltering = false;
        }

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function getColumnIndex(string $column, bool $asFirst = false): string
    {
        $collection = $this->retrieveIndexesFromTable()->where('Column_name', $column);

        if ($asFirst) {
            return $collection->where('Seq_in_index', 1)->firstOrFail()->Key_name;
        }

        return $collection->firstOrFail()->Key_name;
    }

    public function getIndexedColumns(bool $includingPrimary = true): array
    {
        return $includingPrimary ?
            $this->retrieveFirstSeqIndexedColumns() :
            \array_diff($this->retrieveFirstSeqIndexedColumns(), [$this->primaryKey]);
    }

    public function isIndexRequiredOnFiltering(): bool
    {
        return $this->indexRequiredOnFiltering;
    }

    /**
     * @inheritDoc
     */
    protected function setKeysForSelectQuery($query)
    {
        return $this->setKeysForSaveQuery($query);
    }

    /**
     * @inheritDoc
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query->where($this->getPrimaryKeyFilter());
    }

    /**
     * @throws \Exception
     */
    public function getFrozen(): BaseModelFrozenAttributes
    {
        $frozenAttributes =
            \substr($class = static::class, 0, $l = (-1 * (\strlen($class) - \strrpos($class, '\\') - 1))) .
            'Attributes\\' . \substr($class, $l) . 'FrozenAttributes';

        if (\class_exists($frozenAttributes)) {
            return new $frozenAttributes((clone $this)->forceFill($this->toArray()));
        }

        throw new \Exception('Class not found: ' . $frozenAttributes);
    }

    public function shouldReturnNullOnInvalidColumnAttributeAccess(): bool
    {
        return $this->returnNullOnInvalidColumnAttributeAccess;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function getAttribute($key): mixed
    {
        if ((string)$key === '') {
            return null;
        }

        if (\in_array($key, $this->getColumns(), true)) {
            return $this->getAttributeValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * @inheritDoc
     * @throws \Exception
     * @see static::returnNullOnInvalidColumnAttributeAccess
     */
    public function getAttributeValue($key): mixed
    {
        if ($this->exists && isset($this->incrementsToRefresh[$key])) {
            $this->attributes = \array_merge(
                $this->attributes,
                (array)($this->setKeysForSelectQuery($this->newQueryWithoutScopes())
                    ->useWritePdo()
                    ->select($attributes = \array_keys($this->incrementsToRefresh))
                    ->first()
                    ?->toArray())
            );
            $this->syncOriginalAttributes($attributes);
            $this->incrementsToRefresh = [];
        }

        $return = $this->transformModelValue($key, $this->getAttributeFromArray($key, true));

        if (
            $return !== null
            || $this->returnNullOnInvalidColumnAttributeAccess
            || \in_array($key, $this->getColumns(), true)
        ) {
            return $return;
        }

        /** @see static::transformModelValue() */
        if (!$this->hasGetMutator($key)) {
            try {
                return $this->attributes[$key];
            } catch (\Throwable) {
                throw new \Exception('Undefined attribute: ' . $key . ' in model: ' . static::class);
            }
        }

        return null;
    }

    public function shouldEscapeWhenCastingToString(): bool
    {
        return $this->escapeWhenCastingToString;
    }

    public function attributeOffsetUnset(string $offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function attributeOffsetExists(string $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * @inheritDoc
     * @throws \Exception
     * @see static::returnNullOnInvalidColumnAttributeAccess
     */
    public function getRelationValue($key): mixed
    {
        $value = parent::getRelationValue($key);

        if (
            $value === null
            && !$this->isRelation($key)
            && !$this->returnNullOnInvalidColumnAttributeAccess
        ) {
            throw new \Exception('Undefined relation: ' . $key . ' in model: ' . static::class);
        }

        return $value;
    }

    /**
     * This will mass update the whole table if the model does not exist!
     * @inheritDoc
     * @throws \InvalidArgumentException
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method): int
    {
        if (!$this->exists) {
            return $this->newQueryWithoutRelationships()->{$method}($column, $amount, $extra);
        }

        $this->{$column} = $this->isClassDeviable($column)
            ? $this->deviateClassCastableAttribute($method, $column, $amount)
            : (\extension_loaded('bcmath') ? \bcadd(
                $s1 = (string)$this->{$column},
                $s2 = (string)($method === 'increment' ? $amount : $amount * -1),
                \max(\strlen(\strrchr($s1, '.') ?: ''), \strlen(\strrchr($s2, '.') ?: ''))
            ) : $this->{$column} + ($method === 'increment' ? $amount : $amount * -1));

        $this->forceFill($extra);

        if (!$this->isDirty() || $this->fireModelEvent('updating') === false) {
            return 0;
        }

        return (int)tap(
            $this->setKeysForSaveQuery($this->newQueryWithoutScopes())->{$method}($column, $amount, $extra),
            function () use ($column) {
                $this->syncChanges();

                $this->fireModelEvent('updated', false);

                $this->syncOriginalAttributes(\array_keys($this->changes));
                $this->incrementsToRefresh[$column] = true;
            }
        );
    }

    /**
     * Prevent updates
     * Note that relations can be loaded and updated during the lock
     */
    public function lockUpdates(bool $checkDirty = true): bool
    {
        if (
            !$this->exists
            || $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts !== null
            || ($checkDirty && $this->isDirty())
        ) {
            return false;
        }

        $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts = [];

        return true;
    }

    /**
     * Unlock updates
     *
     * To reset the model's $attributes and get the changes from dirty applied during the lock use:
     *
     * if ($this->unlockUpdates()) {
     *  $dirty = $this->getDirty();
     *  $this->attributes = $this->original;
     *  $this->classCastCache = [];
     * }
     *
     * Note that relations can be loaded during the lock
     */
    public function unlockUpdates(): bool
    {
        if ($this->hasUnlockedUpdates()) {
            return false;
        }

        $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts = null;

        return true;
    }

    public function hasUnlockedUpdates(): bool
    {
        return $this->tmpDirtyIfAttributesAreSyncedFromCashedCasts !== [];
    }

    protected function segregatedRelationsDefinitionMap(): array
    {
        $map = [];

        foreach (static::WITH_RELATIONS as $relation) {
            $map[$relation] = \Closure::fromCallable([$this, $relation]);
        }

        return $map;
    }

    public function __clone()
    {
        $this->A = null;
        $this->R = null;
        parent::__clone();
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if ($key === 'r' || $key === 'R') {
            return $this->R ??= BaseModelLazyRelations::getAbstractBaseModelAttributes(\WeakReference::create($this));
        }

        if ($key === 'a' || $key === 'A') {
            return $this->A ??= BaseModelLazyAttributes::getAbstractBaseModelAttributes(\WeakReference::create($this));
        }

        return $this->getAttribute($key);
    }

    public function __sleep()
    {
        $this->A = null;
        $this->R = null;

        return parent::__sleep();
    }

    public function __wakeup()
    {
        $this->A = null;
        $this->R = null;
        parent::__wakeup();
    }
}
