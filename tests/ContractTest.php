<?php

namespace MacropaySolutions\CrufdWizard\Test;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Database\Connection;
use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Relations\Relation;
use MacropaySolutions\Kernel\Http\JsonResponse;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Pagination\CursorPaginator;
use MacropaySolutions\Kernel\Pagination\LengthAwarePaginator;
use MacropaySolutions\Kernel\Pagination\Paginator;
use MacropaySolutions\CrufdWizard\Http\Controllers\ResourceControllerTrait;
use MacropaySolutions\CrufdWizard\Models\BaseModel;
use MacropaySolutions\CrufdWizard\Services\BaseResourceService;
use PHPUnit\Framework\TestCase;

class ContractTest extends TestCase
{
    public static ?string $sql = null;
    public static ?array $bindings = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock application that extends the real Container 
        // so it has the environment() method required by BaseModel
        $app = new class () extends Container {
            public function environment(string $env): bool {
                return false;
            }
        };

        $app->bind('log', function () {
            return new class () {
                public function warning(string $message): void {}
                public function error(string $message): void {}
            };
        });

        $app->bind('cache.store', function () {
            return new class () {
                public function remember(string $key, $ttl, \Closure $callback): mixed {
                    return $callback();
                }
            };
        });

        $app->bind('translator', function () {
            return new class () {
                public function get(string $key): string { return $key; }
                public function trans(string $key): string { return $key; }
                public function choice(string $key): string { return $key; }
            };
        });

        $app->bind('request', function () {
            return Request::create('/');
        });

        Container::setInstance($app);
    }

    public function testFilter(): void
    {
        $controller = new class () {
            use ResourceControllerTrait;

            public function __construct()
            {
                $this->init();
            }

            protected function setModelFqnToControllerMap(): void
            {
                $this->modelFqnToControllerMap = [];
            }

            protected function getPaginator(
                Relation|Builder $builder,
                array $allRequest
            ): LengthAwarePaginator|Paginator|CursorPaginator {
                ContractTest::$sql = $builder->toSql();
                ContractTest::$bindings = $builder->getBindings();

                // this will just return empty list
                throw new \Exception();
            }

            /**
             * @inheritDoc
             */
            protected function setResourceService(): void
            {
                $this->resourceService = new  class () extends BaseResourceService {
                    /**
                     * @inheritDoc
                     */
                    protected function setBaseModel(): void
                    {
                        $this->model = new class () extends BaseModel {
                            protected bool $indexRequiredOnFiltering = false;
                            protected $fillable = [
                                'column1',
                                'column2',
                            ];
                            protected $table = 'test';

                            protected function newBaseQueryBuilder(): \MacropaySolutions\Kernel\Database\Query\Builder
                            {
                                return (new Connection(new \PDO('sqlite::memory:')))->query();
                            }
                        };
                    }
                };
            }
        };

        $request = new Request([
            'column1' => 5,
            'column2' => 3,
            'column3' => 3,
            'sort' => [['by' => 'column2'], ['by' => 'column1', 'dir' => 'ASC'], ['by' => 'column3', 'dir' => 'ASC']],
            'limit' => 50,
        ]);
        self::assertInstanceOf(JsonResponse::class, $response = $controller->list($request));
        self::assertEquals([
            'has_more_pages' => false,
            'sums' => [],
            'avgs' => [],
            'mins' => [],
            'maxs' => [],
            'current_page' => 1,
            'data' => [],
            'from' => null,
            'last_page' => 1,
            'per_page' => 50,
            'to' => null,
            'total' => 0
        ], $response->getData(true));

        self::assertEquals(
            'select * from "test" where "column1" = ? and "column2" = ? order by "column2" desc, "column1" asc',
            static::$sql
        );
        static::$sql = null;
        self::assertEquals([5, 3], static::$bindings);
        static::$bindings = null;
    }
}
