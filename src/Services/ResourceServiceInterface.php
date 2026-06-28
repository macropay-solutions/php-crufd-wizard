<?php

namespace MacropaySolutions\CrufdWizard\Services;

use MacropaySolutions\CrufdWizard\Models\BaseModel;
use MacropaySolutions\Kernel\Database\Obvious\Builder;

interface ResourceServiceInterface
{
    public function getResourceName(): string;

    /**
     * @throws \Throwable
     */
    public function list(array $request): Builder;

    /**
     * @throws \Throwable
     */
    public function create(array $request): BaseModel;

    /**
     * @throws \Throwable
     */
    public function get(string $identifier, array $withRelations = [], bool $appendIndex = true): BaseModel;

    /**
     * @throws \Throwable
     */
    public function update(string $identifier, array $request): BaseModel;

    /**
     * @throws \Throwable
     */
    public function delete(string $identifier): bool;

    public function isUpdateOrCreateAble(array $requestBody): bool;

    public function getTableName(): string;

    public function getIndexRequiredOnFiltering(): array;

    public function getModelColumns(bool $includingPrimaryKeyProperty = false): array;

    public function getIgnoreExternalUpdateFor(): array;

    public function getIgnoreExternalCreateFor(): array;
}
