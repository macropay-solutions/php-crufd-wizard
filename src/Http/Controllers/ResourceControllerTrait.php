<?php

namespace MacropaySolutions\CrufdWizard\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;
use MacropaySolutions\CrufdWizard\Exceptions\CrudValidationException;
use MacropaySolutions\CrufdWizard\Helpers\GeneralHelper;
use MacropaySolutions\CrufdWizard\Helpers\ResourceHelper;
use MacropaySolutions\CrufdWizard\Models\BaseModel;
use MacropaySolutions\CrufdWizard\Services\ResourceServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Call $this->>init() on the constructor where this trait is used
 */
trait ResourceControllerTrait
{
    protected string $label = '';
    protected bool $simplePaginate = false;
    protected array $modelFqnToControllerMap = [];

    /**
     * If a related resource is not exposed via crud in $modelFqnToControllerMap,
     *     it can be exposed in this property
     */
    protected array $relatedModelFqnToControllerMap = [];
    protected ResourceServiceInterface $resourceService;
    protected array $paginationKeys = [
        'current_page',
        'data',
        'from',
        'last_page',
        'per_page',
        'to',
        'total',
    ];
    protected bool $forbidCreate = false;
    protected bool $forbidGet = false;
    protected bool $forbidList = false;
    protected bool $forbidUpdate = false;
    protected bool $forbidDelete = true;

    /**
     * @throws \Throwable
     */
    protected function init(): void
    {
        $this->setModelFqnToControllerMap();
        $this->setResourceService();
        $this->label = $this->resourceService->getResourceName();
    }

    public function list(Request $request): Response
    {
        $allRequest = $request->all();
        $this->setSimplePaginate($allRequest);

        return $this->handleList($allRequest, $request);
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $this->throwIfForbidden($this->forbidCreate);
            /** $request can contain also files so, overwrite this function to handle them */

            return GeneralHelper::app(JsonResponse::class, [
                'data' => $this->resourceService->create($this->validateCreateRequest(
                    $request->forceReplace(GeneralHelper::filterDataByKeys(
                        $request->all(),
                        \array_diff(
                            $this->resourceService->getModelColumns(),
                            $this->resourceService->getIgnoreExternalCreateFor()
                        )
                    ))
                ))->toArray(),
                'status' => 201
            ]);
        } catch (ValidationException | CrudValidationException $e) {
            return GeneralHelper::app(JsonResponse::class, [
                'data' => [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()
                ],
                'status' => 400
            ]);
        } catch (\Throwable $e) {
            \app('log')->error($this->label . ' create error = ' . $e->getMessage());

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function get(string $identifier, Request $request): JsonResponse
    {
        try {
            $this->throwIfForbidden($this->forbidGet);
            $baseModel = $this->resourceService->get(
                $identifier,
                $this->getFilteredRelations((array)$request->get('withRelations'))
            );
            $result = $baseModel->toArray();
            $countRelations = $this->getFilteredRelations((array)$request->get('withRelationsCount'), $baseModel);

            foreach ($countRelations as $relationName) {
                $result[$relationName . $this->resourceService::COUNT_ALIAS_POSTFIX] ??=
                    $baseModel->{$relationName}()->count();
            }

            foreach (
                $this->getFilteredRelations((array)$request->get('withRelationsExistence'), $baseModel) as $relationName
            ) {
                $result[
                    $relationName . $this->resourceService::EXIST_ALIAS_POSTFIX
                ] ??= \in_array($relationName, $countRelations, true) &&
                    \is_int($result[$relationName . $this->resourceService::COUNT_ALIAS_POSTFIX] ?? null) ?
                    $result[$relationName . $this->resourceService::COUNT_ALIAS_POSTFIX] > 0 :
                    $baseModel->{$relationName}()->exists();
            }

            return GeneralHelper::app(JsonResponse::class, [
                'data' => $result,
                'status' => 200
            ]);
        } catch (\Throwable $e) {
            if (!$e instanceof ModelNotFoundException) {
                \app('log')->error($this->label . ' get for identifier: ' . $identifier . ', error = ' . $e->getMessage());
            }

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function update(string $identifier, Request $request): JsonResponse
    {
        try {
            $this->throwIfForbidden($this->forbidUpdate);
            $all = $request->all();

            try {
                $toFill = GeneralHelper::filterDataByKeys($all, \array_diff(
                    $this->resourceService->getModelColumns(),
                    $this->resourceService->getIgnoreExternalUpdateFor()
                ));

                if ($toFill === []) {
                    return GeneralHelper::app(JsonResponse::class, [
                        'data' => $this->resourceService->get($identifier)->toArray(),
                        'status' => 200
                    ]);
                }

                $model = $this->resourceService->get($identifier, appendIndex: false)->fill($toFill);
                /** $request can contain also files so, overwrite this function to handle them */
                $request->forceReplace($model->getDirty(\array_keys($toFill)));

                return GeneralHelper::app(JsonResponse::class, [
                    'data' => $this->resourceService->update($identifier, $this->validateUpdateRequest($request))
                        ->toArray(),
                    'status' => 200
                ]);
            } catch (ModelNotFoundException $e) {
                if (!$this->resourceService->isUpdateOrCreateAble($all)) {
                    throw $e;
                }

                $request->forceReplace($all);

                return $this->create($request);
            }
        } catch (ValidationException | CrudValidationException $e) {
            return GeneralHelper::app(JsonResponse::class, [
                'data' => [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()
                ],
                'status' => 400
            ]);
        } catch (\Throwable $e) {
            \app('log')->error($this->label . ' update for identifier: ' . $identifier . ', error = ' . $e->getMessage());

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function delete(string $identifier): JsonResponse
    {
        try {
            $this->throwIfForbidden($this->forbidDelete);

            return GeneralHelper::app(JsonResponse::class, [
                'status' => $this->resourceService->delete($identifier) ? 204 : 400
            ]);
        } catch (\Throwable $e) {
            if (!$e instanceof ModelNotFoundException) {
                \app('log')->error($this->label . ' delete for identifier: ' . $identifier . ', error = ' . $e->getMessage());
            }

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    protected function getFilteredRelations(array $relations, ?BaseModel $baseModel = null): array
    {
        return \array_intersect(($baseModel instanceof BaseModel ?
            $baseModel :
            $this->getResourceAsModelFQN())::WITH_RELATIONS, $relations);
    }

    /**
     * @throws \Exception
     */
    protected function validateRelation(string $relation): void
    {
        if (!\in_array($relation, $this->getResourceAsModelFQN()::WITH_RELATIONS, true)) {
            throw new \Exception('Relation as subresource not found for this resource.');
        }
    }

    protected function handleList(array $allRequest, Request $request): Response
    {
        try {
            $this->throwIfForbidden($this->forbidList);
            $paginator = $this->getPaginator(
                $this->resourceService->list($allRequest),
                $allRequest
            );

            if ([] !== $indexRequiredOnFiltering = $this->resourceService->getIndexRequiredOnFiltering()) {
                $appends['index_required_on_filtering'] = $indexRequiredOnFiltering;
            }

            return $this->getJsonResponse($paginator, $appends ?? []);
        } catch (\Throwable $e) {
            if (isset($allRequest['logError'])) {
                \app('log')->error($this->label . ' list for ' . \json_encode($allRequest) . ', error = ' . $e->getMessage());
            }

            return $this->getEmptyPaginatedResponse($allRequest);
        }
    }

    public function getRelated(
        Request $request,
        string $identifier,
        string $relation,
        string $relatedIdentifier
    ): JsonResponse {
        try {
            return $this->getRelatedController($identifier, $relation, $relatedIdentifier)
                ->get($relatedIdentifier, $request);
        } catch (\Throwable $e) {
            if (!$e instanceof ModelNotFoundException) {
                \app('log')->error($this->label . '/' . $identifier . '/' . $relation . '/' . $relatedIdentifier .
                    ', error = ' . $e->getMessage());
            }

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function updateRelated(
        Request $request,
        string $identifier,
        string $relation,
        string $relatedIdentifier
    ): JsonResponse {
        try {
            return $this->getRelatedController($identifier, $relation, $relatedIdentifier)
                ->update($relatedIdentifier, $request);
        } catch (\Throwable $e) {
            if (!$e instanceof ModelNotFoundException) {
                \app('log')->error($this->label . '/' . $identifier . '/' . $relation . '/' . $relatedIdentifier .
                    ', error = ' . $e->getMessage());
            }

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function deleteRelated(string $identifier, string $relation, string $relatedIdentifier): JsonResponse
    {
        try {
            return $this->getRelatedController($identifier, $relation, $relatedIdentifier)
                ->delete($relatedIdentifier);
        } catch (\Throwable $e) {
            if (!$e instanceof ModelNotFoundException) {
                \app('log')->error($this->label . '/' . $identifier . '/' . $relation . '/' . $relatedIdentifier .
                    ', error = ' . $e->getMessage());
            }

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    protected function getResourceAsModelFQN(): string
    {
        $resource = ResourceHelper::getResourceControllerToModelFQNMap($this->modelFqnToControllerMap)[$this::class] ??
            null;

        if (\is_string($resource)) {
            return $resource;
        }

        throw new \Exception('Could not getResourceAsModelFQN.');
    }

    protected function getEmptyPaginatedResponse(array $request): JsonResponse
    {
        $data = [
            'items' => [],
            'perPage' => \max(1, $request['limit'] ?? 15),
            'currentPage' => 1,
        ];

        return $this->getJsonResponse(
            $this->simplePaginate ?
                GeneralHelper::app(Paginator::class, $data)->hasMorePagesWhen(false) :
                GeneralHelper::app(LengthAwarePaginator::class, \array_merge($data, ['total' => 0])),
            ['sums' => [], 'avgs' => [], 'mins' => [], 'maxs' => []]
        );
    }

    protected function getJsonResponse(
        LengthAwarePaginator | Paginator | CursorPaginator $paginator,
        array $appends = []
    ): JsonResponse {
        return GeneralHelper::app(JsonResponse::class, [
            'data' => \array_merge($paginator instanceof CursorPaginator ? [
                'cursor' => $paginator->nextCursor()?->encode(),
            ] : [], [
                'has_more_pages' => $paginator->hasMorePages(),
            ], $appends, GeneralHelper::filterDataByKeys(
                $paginator->toArray(),
                $this->paginationKeys
            )),
            'status' => 200
        ]);
    }

    /**
     * @throws \Throwable
     */
    protected function validateCreateRequest(Request $request): array
    {
        throw new \Exception('Development error. validateCreateRequest not implemented');
    }

    /**
     * @throws \Throwable
     */
    protected function validateUpdateRequest(Request $request): array
    {
        throw new \Exception('Development error. validateUpdateRequest not implemented');
    }

    /**
     * @throws \Throwable
     */
    protected function setResourceService(): void
    {
        throw new \Exception('Development error. setResourceService not implemented');
    }

    /**
     * @throws \Throwable
     */
    protected function setModelFqnToControllerMap(): void
    {
        throw new \Exception('Development error. setModelFqnToControllerMap not implemented');
    }

    protected function setSimplePaginate(array $allRequest): void
    {
        $this->simplePaginate = isset($allRequest['simplePaginate'])
            || isset($allRequest['cursor']) ? true : $this->simplePaginate;
    }

    /**
     * @throws \Throwable
     */
    protected function getPaginator(
        Builder | Relation $builder,
        array $allRequest
    ): LengthAwarePaginator | Paginator | CursorPaginator {
        $model = $builder instanceof Relation ? $builder->getRelated() : $builder->getModel();
        $limit = \max(1, (int)($allRequest['limit'] ?? $model->getPerPage()));
        $listUnHydrated = false;

        if ($model::LIST_UN_HYDRATED_WHEN_POSSIBLE) {
            /** @var BaseModel $first */
            $first = $model->exists ? $model : ($model::query()->first() ?? $model);
            $listUnHydrated = $builder->getEagerLoads() === []
                && \array_diff_key(
                    $first->attributesToArray(),
                    ['primary_key_identifier' => null]
                ) === $first->getRawOriginal();
        }

        if (isset($allRequest['cursor'])) {
            if ($listUnHydrated) {
                return $builder->cursorPaginateUnHydrated(
                    $limit,
                    ['*'],
                    'cursor',
                    $allRequest['cursor']
                );
            }

            return $builder->cursorPaginate(
                $limit,
                ['*'],
                'cursor',
                $allRequest['cursor']
            );
        }

        if ($listUnHydrated) {
            if ($this->simplePaginate) {
                return $builder->simplePaginateUnHydrated(
                    $limit,
                    ['*'],
                    'page',
                    \max((int)($allRequest['page'] ?? 1), 1)
                );
            }

            return $builder->paginateUnHydrated(
                $limit,
                ['*'],
                'page',
                \max((int)($allRequest['page'] ?? 1), 1)
            );
        }

        return $builder->{$this->simplePaginate ? 'simplePaginate' : 'paginate'}(
            $limit,
            ['*'],
            'page',
            \max((int)($allRequest['page'] ?? 1), 1)
        );
    }

    /**
     * @throws \Exception
     */
    protected function throwIfForbidden(bool $condition): void
    {
        if ($condition) {
            throw new \Exception('Forbidden');
        }
    }

    /**
     * @return self
     * @throws \Throwable
     */
    protected function getRelatedController(
        string $identifier,
        string $relation,
        string $relatedIdentifier
    ): object {
        $related = $this->getRelatedFromRelation($identifier, $relation, $relatedIdentifier);
        $controllerFqn = (string)($this->modelFqnToControllerMap[$related::class] ??
            ($this->relatedModelFqnToControllerMap[$related::class] ?? ''));

        if ('' === $controllerFqn) {
            throw new \Exception('Related ' . $relation . ' not exposed as resource.');
        }

        return GeneralHelper::app($controllerFqn);
    }

    /**
     * @throws \Throwable
     */
    protected function getRelatedFromRelation(
        string $identifier,
        string $relation,
        string $relatedIdentifier
    ): BaseModel {
        $this->validateRelation($relation);
        /** @var Relation $relationInstance */
        $relationInstance = $this->resourceService->get($identifier, appendIndex: false)->{$relation}();
        /** @var BaseModel $related */
        $related = $relationInstance->getRelated();
        $exploded = \explode($related::COMPOSITE_PK_SEPARATOR, $relatedIdentifier);
        $pks = [];

        foreach ($related->getPrimaryKeyFilter() as $column => $value) {
            if (!\is_array($value) && \is_string($column)) {
                $pks[] = $related->qualifyColumn($column);

                continue;
            }

            $pks[] = $related->qualifyColumn(\reset($value));
        }

        if (
            \count($pks) !== \count($exploded)
            || !$relationInstance->where(\array_combine($pks, $exploded))->exists()
        ) {
            throw (new ModelNotFoundException())->setModel($related::class);
        }

        return $related;
    }
}
