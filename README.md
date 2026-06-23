# php-crufd-wizard - RetrieveQL

[![Build Status](https://github.com/macropay-solutions/php-crufd-wizard/actions/workflows/tests.yml/badge.svg)](https://github.com/macropay-solutions/php-crufd-wizard/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/macropay-solutions/php-crufd-wizard)](https://packagist.org/packages/macropay-solutions/php-crufd-wizard)
[![Latest Stable Version](https://img.shields.io/packagist/v/macropay-solutions/php-crufd-wizard)](https://packagist.org/packages/macropay-solutions/php-crufd-wizard)
[![License](https://img.shields.io/packagist/l/macropay-solutions/php-crufd-wizard)](https://packagist.org/packages/macropay-solutions/php-crufd-wizard)


## Features: Freemium vs Pro High Level Comparison

| Feature / Operation | RetrieveQL Freemium (`php-crufd-wizard`)                       | RetrieveQL Pro (`php-rest-wizard`) |
| :--- |:------------------------------------------------------------------| :--- |
| **Data Engine Support** | ✅ SQL Databases Only                                              | ✅ SQL + **Elasticsearch (via SQL Driver)** |
| **Create Resource** | ✅ Standard Create                                                 | ✅ Standard Create |
| **Read / Get Resource** | ✅ Single retrieve with relations                                  | ✅ Single retrieve with relations |
| **Update Resource** | ✅ Standard Update                                                 | ✅ Update, **Upsert**, & **Incrementing** (`++x.xx`) |
| **Delete Resource** | ✅ Standard Delete                                                 | ✅ Standard Delete |
| **Bulk Delete Resource** | ❌                                                                 | ✅ Via `list({filters})->delete()` |
| **List Resource** | ✅ Standard List                                                   | ✅ Standard List |
| **List Relation as Resource** | ❌                                                                 | ✅ (e.g., `GET /{resource}/{pk}/{relation}?`) |
| **Composite Primary Key** | ✅ Supported (e.g., `12_35`)                                       | ✅ Supported (e.g., `12_35`) |
| **Default Filtering (`=`)** | ✅ **Strict Match** (Exact equality)                               | ✅ **Starts With** (Prefix match by default) |
| **Advanced Filters** | ✅ `from`, `to`                                                     | ✅ `equals`, `in`, `not in`, `from`, `to`, `contains`, `notContains`, `is null`, `is not null` |
| **Response Formats** | ✅ JSON                                                            | ✅ JSON, **JSONL** (`limit=-1`), **StreamedJsonResponse** |
| **Pagination** | ✅ LengthAware, Simple, Cursor                                     | ✅ All Free + `limit=0` (Count only) |
| **Sorting** | ✅ Multi-column sorting                                            | ✅ Multi-column & **Aggregate Sorting** |
| **Relational Loading** | ✅ `withRelations`, `withRelationsCount`, `withRelationsExistence` | ✅ All Free + `with appends`, `with distinct relations` |
| **Relation Constraints** | ❌                                                                 | ✅ `has relations`, `doesn't have relations`, `has distinct relations` |
| **Custom Deep Relations** | ❌                                                                 | ✅ `HasManySelfThroughSelf`, `HasManyThrough2LinkTables`, `HasManyThrough3LinkTables`, `HasOneSelfThroughSelf`, `HasOneThrough2LinkTables`, `HasOneThrough3LinkTables` |
| **Aggregations** | ❌                                                                 | ✅ Sums, Averages, Minimums, Maximums, Distinct column(s) fetching |
| **Elasticsearch KPIs** | ❌                                                                 | ✅ **Real-time Aggregations** on ES Indexes |
| **Group By & Subtotals** | ❌                                                                 | ✅ Group By, Sub-totals, Sub-averages, Sub-minimums, Sub-maximums |
| **Group Constraints** | ❌                                                                 | ✅ Count Distinct, Group Count, Havings (including relation counts) |
| **DB Protection / Guardrails** | ❌                                                                 | ✅ API Timeout blocks (MySQL/MariaDB), MVCC `select count(*)` slow query fix |



#### The Freemium Advantage
With the free version, you can instantly pull resources, load their relationships, check if relationships exist, and paginate through large datasets.
* *Example (Free):* `GET /operations?currency=EUR&withRelationsCount[]=products` (Get all operations where currency exactly equals EUR and include the count of related products).

#### The Pro Advantage
When your data logic gets complex, the paid version of RetrieveQL replaces hundreds of lines of Eloquent sub-queries, aggregations, and groupings. It also includes built-in safeguards against API-triggered database blocking.
* *Example (Pro):* `GET /operations?currency[in][]=EUR&currency[in][]=USD&withSum=value` (Get operations where currency is IN [EUR, USD] and include the total sum of their values).

## Url query language lib for RESTful CRUD (micro) services

### This is not just another CRUD lib!
It has built in filtering capabilities that can be used for listing but also for mass deleting, so it could be called a CRU**F**D (create, read, update, **filter** and delete) lib instead.



I. [Install](#i-install)

II. [Start using it](#ii-start-using-it)

III. [Crud routes](#iii-crud-routes)

III.1. [Create resource](#iii1-create-resource)

III.2. [Get resource](#iii2-get-resource)

III.3. [List filtered resource](#iii3-list-filtered-resource)

III.4. [Update resource (or create)](#iii4-update-resource-or-create)

III.5. [Delete resource](#iii5-delete-resource)



## I. Install

    composer require macropay-solutions/php-crufd-wizard


## II. Start using it


Look at

`\MacropaySolutions\CrufdWizard\Providers\CrufdProvider`. Its logic is already in PHP Framework registerExplicitBindingsMap and config.


Create a constant in your code

```php
    class DbCrudMap
    {
        public const MODEL_FQN_TO_CONTROLLER_MAP = [
            BaseModelChild::class => ResourceControllerTraitIncludedChild::class,
            ...
        ];
    }
```

Use https://github.com/macropay-solutions/php-crufd-wizard-generator to generate the model, service and controller or extend:

- BaseModel (it needs datetime NOT timestamp for created_at and updated_at columns)
- BaseResourceService

Create a Controller that uses ResourceControllerTrait and call `$this->init();` from its __construct.

Optionally if you don't want to expose some models as resources but, you want to expose them as relation on an exposed resource then define in your Base Controller or ResourceController:

```php
    protected array $relatedModelFqnToControllerMap = [
        RelatedBaseModelChild::class => RelatedResourceControllerTraitIncludedChild::class,
    ];
````

**Active Record Segregation of Properties:** for model attributes(columns) autocomplete and to avoid clashes with the model properties or relations:

- extend BaseModelAttributes following the same FQN structure as the parent's:

      \App\Models\ChildBaseModel paired with \App\Models\Attributes\ChildBaseModelAttributes
      \App\Models\Folder\ChildBaseModel paired with \App\Models\Folder\Attributes\ChildBaseModelAttributes

- add in its class dock block using **@property** all the models properties/attributes/columns
- add in the model's class dock block **@property ChildBaseModelAttributes $a** and **@mixin ChildBaseModelAttributes**
- use `$model->a->` instead of `$model->` _(this will work without autocomplete even if you don't do the above)_

**Active Record Segregation of Properties:** for model relation autocomplete and to avoid clashes with the model properties and attributes(columns):

- extend BaseModelRelations following the same FQN structure as the parent's:

      \App\Models\ChildBaseModel paired with \App\Models\Attributes\ChildBaseModelRelations
      \App\Models\Folder\ChildBaseModel paired with \App\Models\Folder\Attributes\ChildBaseModelRelations

- add in its class dock block using **@property-read** all the models relations
- add in the model's class dock block **@property ChildBaseModelRelations $r** and **@mixin ChildBaseModelRelations**
- use `$model->r->` instead of `$model->` _(this will work without autocomplete even if you don't do the above)_
- BaseModelFrozenAttributes can be also extended on the same logic and used for model read only situations - DTO without setters (Reflection or Closure binding usage will retrieve/set protected stdClass not Model - but the model can be retrieved from DB by its primary key that is readable in this frozen model):
```php
#OperationModel example for BaseModelFrozenAttributes
    public function getFrozen(): OperationFrozenAttributes
    {
        return parent::getFrozen(); // this is needed for autocompletion and will include also the loaded relations
        // or
        return new OperationFrozenAttributes((clone $this)->forceFill($this->toArray()));
        // or just attributes without loaded relations
        return new OperationFrozenAttributes((clone $this)->forceFill($this->attributesToArray())); 
    }
```

```php
#OperationService example for BaseModelAttributes and BaseModelFrozenAttributes
    public function someFunction(): void
    {
         // BaseModelAttributes
         echo $this->model-a->value; // has autocomplete - will print for example 1
         echo $this->model-a->value = 10; // has autocomplete - will print 10
         echo $this->model->value; // has autocomplete - will print 10

         // BaseModelFrozenAttributes
         $dto = $this->model->getFrozen();
         echo $dto->client_id; // has autocomplete - will print for example 1
         $dto->client_id = 4; // Exception: Dynamic properties are forbidden.

         if (isset($dto->client)) {
             /** @var ClientFrozenAttributes $client */
             // $client will be an stdClass that has autocomplete like a ClientFrozenAttributes
             $client = $dto->client;
             echo $client->name; // has autocomplete - will print for example 'name'
             $client->name = 'text'; // NO Exception
             echo $client->name; // will print 'text'
             // $client changes can happen, but they will not be persisted in the $dto ($client is a stdClass clone)
             echo $dto->client->name; // will print 'name'
             echo $dto->client->name = 'text'; // will print 'text'
             echo $dto->client->name; // will print 'name'
         }

         foreach (($dto->products ?? []) as $k => $product) {
             /** @var ProductFrozenAttributes $product */
             // $product will be an stdClass that has autocompletes like a ProductFrozenAttributes
             echo $product->value; // has autocomplete - will print for example 1
             $product->value = 2; // NO Exception
             echo $product->value; // will print 2
             // $product changes can happen, but they will not be persisted in the $dto ($product is a stdClass clone)
             echo $dto->products[$k]->value; // will print 1
             echo $dto->products[$k]->value = 2; // will print 2
             echo $dto->products[$k]->value; // will print 1
         }
    }
```

Add this new resource to the above map.

Register the crud routes in your application using:

```php

    try {
        foreach (
            ResourceHelper::getResourceNameToControllerFQNMap(
                DbCrudMap::MODEL_FQN_TO_CONTROLLER_MAP
            ) as $resource => $controllerFqn
        ) {
            $controllerFqnExploded = \explode('\\', $controllerFqn);
            $controller = \end($controllerFqnExploded);
            //$router->get('/' . $resource . '/{identifier}/{relation}', [
            //    'as' => $resource . '.listRelated',
            //    'uses' => $controller . '@listRelation',
            //]); // paid version only
            $router->get('/' . $resource, [
                'as' => $resource . '.list',
                'uses' => $controller . '@list',
            ]);
            //$router->post('/' . $resource . '/{identifier}/{relation}/l/i/s/t', [
            //    'as' => $resource . '.post_listRelated',
            //    'uses' => $controller . '@listRelation',
            //]); // paid version only
            $router->post('/' . $resource . '/l/i/s/t', [
                'as' => $resource . '.post_list',
                'uses' => $controller . '@list',
            ]);
            $router->post('/' . $resource, [
                'as' => $resource . '.create',
                'uses' => $controller . '@create',
            ]);
            $router->put('/' . $resource . '/{identifier}', [
                'as' => $resource . '.update',
                'uses' => $controller . '@update',
            ]);
            $router->get('/' . $resource . '/{identifier}', [
                'as' => $resource . '.get',
                'uses' => $controller . '@get',
            ]);
            $router->delete('/' . $resource . '/{identifier}', [
                'as' => $resource . '.delete',
                'uses' => $controller . '@delete',
            ]);

            $router->get('/' . $resource . '/{identifier}/{relation}/{relatedIdentifier}', [
                'as' => $resource . '.getRelated',
                'uses' => $controller . '@getRelated',
            ]);
            $router->put('/' . $resource . '/{identifier}/{relation}/{relatedIdentifier}', [
                'as' => $resource . '.updateRelated',
                'uses' => $controller . '@updateRelated',
            ]);
            $router->delete('/' . $resource . '/{identifier}/{relation}/{relatedIdentifier}', [
                'as' => $resource . '.deleteRelated',
                'uses' => $controller . '@deleteRelated',
            ]);
        }
    } catch (Throwable $e) {
        \app('log')->error($e->getMessage());
    }
```

OBS

Set `$returnNullOnInvalidColumnAttributeAccess = false;` in model if you want exception instead of null on accessing invalid model attributes or invalid relations (It also needs error_reporting = E_ALL in php ini file).

Set `LIST_UN_HYDRATED_WHEN_POSSIBLE = true` in model if you want to skip eloquent hydration for list db query results; note that setting this to true will not append the primary_key_identifier on response. Also if you use casts or any logic that alters (conditionally or not) the attributes of the model, you should leave LIST_UN_HYDRATED_WHEN_POSSIBLE as false.

Set LIVE_MODE=false in your .env file for non prod environments.

Use Request::getFiltered macro to sanitize data retrieved from request
```php
(string)\request('signature', '');
```
The above will throw Array to string conversion error for query: _?signature[]=_
The proper way of handling it before:
```php
(string)\filter_var(\request('signature', ''), \FILTER_DEFAULT);
```
The new way of handling it:
```php
(string)\request()->getFiltered('signature', '');
```


### III. Crud routes
```The identifier can be a primary key or a combination of primary keys with _ between them if the resource has a combined primary key!!!```

see \MacropaySolutions\CrufdWizard\Models\BaseModel::COMPOSITE_PK_SEPARATOR


#### III.1 Create resource
**POST** /{resource}

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json
      
      ContentType: application/json

body:

      {
         "column_name":"value",
         ...
      }

Json Response:

201:

    {
        "column_name":"value",
        ...
    }


400:

    {
        "message": "The given data was invalid.", // or other message
        "errors": {
            "column_name1": [
                "The column name 1 field is required."
            ],
            "column_name_2": [
               "The column name 2 field is required."
            ],
            ...
         }
    }

The above "errors" are optional and appear only for validation errors while "message" will always be present.


#### III.2 Get resource
**GET** /{resource}/{identifier}?withRelations[]=has_manyRelation&withRelations[]=has_oneRelation&withRelationsCount[]=has_manyRelation&withRelationsExistence[]=has_manyRelation

**GET** /{resource}/{identifier}/{relation}/{relatedIdentifier}?withRelations[]=has_manyRelation&withRelations[]=has_oneRelation&withRelationsCount[]=has_manyRelation&withRelationsExistence[]=has_manyRelation

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json

Use **POST** LIST requests if the identifier contains **sensitive data**

Json Response:

200:

    {
        "identifier":"value",
        "column_name":"value",
        ...
        "index_required_on_filtering": [
           "column_name_1",
           "column_name2"
        ],
        "has_oneRelation":{...},
        "has_manyRelation":[
            {
                "id": ...,
                "name": "...",
                "pivot": {
                   "key1": 25,
                   "key2": 5
                }
            }
        ],
        "has_manyRelation_count": 0,
        "has_manyRelation_exist": false
    }

400:

    {
        "message": ...
    }


The identifier can be composed by multiple identifiers for pivot resources that have composite primary key.
Example:/table1-table2-pivot/3_10

The relations will be retrieved as well when required. The relation keys CAN'T be used for filtering!!!

```index_required_on_filtering``` key CAN'T be used for filtering.

```pivot``` is optional and appears only on relations that are tied via a pivot.

#### III.3 List filtered resource
**GET** /{resource}?page=1&limit=10&column=2&sort[0][by]=updated_at&sort[0][dir]=ASC&withRelations[]=has_manyRelation&withRelations[]=has_oneRelation&withRelationsCount[]=has_manyRelation&withRelationsExistence[]=has_manyRelation&updated_at[from]=2026-05-25 00:00:00&updated_at[to]=2026-05-25 23:59:59

**POST** /{resource}/l/i/s/t

**GET** /{resource}/{identifier}/{relation}?... // available only in paid version

**POST** /{resource}/{identifier}/{relation}/l/i/s/t // available only in paid version

Advanced filters and aggregations are available only in the paid version

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json

      Content-Type: application/json OR application/x-www-form-urlencoded // for POST

Body for POST:

    {"page":"1","limit":"10","column":"2","sort":[{"by":"updated_at","dir":"ASC"}],"withRelations":["has_manyRelation","has_oneRelation"],"withRelationsCount":["has_manyRelation"],"withRelationsExistence":["has_manyRelation"]}

OR

    page=1&limit=10&column=2&sort[0][by]=updated_at&sort[0][dir]=ASC&withRelations[]=has_manyRelation&withRelations[]=has_oneRelation&withRelationsCount[]=has_manyRelation&withRelationsExistence[]=has_manyRelation

Use **POST** requests if GET returns this error message: **Request Header Or Cookie Too Large**

or if the filters contain **sensitive data**

Json Response:

200:

    {
        "index_required_on_filtering": [
           "column_name1",
           "column_name2"
        ],
        "current_page": 1, // not present when cursor is present in request 
        "data": [
            {
               "identifier":"value",
               "column_name":"value",
               ...,
              "has_oneRelation":{...},
              "has_manyRelation":[
                  {
                      "id": ...,
                      "name": "...",
                      "pivot": {
                         "key1": 25,
                         "key2": 5
                      }
                  }
              ],
              "has_manyRelation_count": 0,
              "has_manyRelation_exist": false
            }
        ],
        "from": 1, // not present when cursor is present in request
        "last_page": 1, // not present when cursor is present in request or when simplePaginate is true in controller or present in request
        "per_page": 10,
        "to": 1, // not present when cursor is present in request
        "total": 1, // not present when cursor is present in request or simplePaginate is true in controller or present in request
        "has_more_pages": bool,
        "cursor": "..." // present only when cursor is present in request
    }


The reserved words / parameters that will be used as query params are:

        page,
        limit,
        simplePaginate
        cursor,
        sort,
        withRelations,
        withRelationsCount,
        withRelationsExistence,


Defaults:

    page=1;
    limit=15;
    simplePaginate is false by default and only its presence is checked in request, not its value
    cursor is not defined
    sort[][dir]=DESC

Obs.

    index_required_on_filtering key CAN'T be used for filtering.
    use ?cursor= for cursor pagination and ?simplePaginate=1 for simplePaginate. Use none of them for length aware paginator.
    if \Illuminate\Http\Middleware\ConvertEmptyStringsToNull::class is used use ?cursor=1 instead of emtpy string
    sort works also on aggregated colums for relation count and existence 
    withRelations which uses with function does not load morphable relations. BaseResourceService::addRelationsToExistingModel can be used for those or loadMorph.


#### III.4 Update resource (or create)
**PUT** /{resource}/{identifier}

**PUT** /{resource}/{identifier}/{relation}/{relatedIdentifier}

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json
      
      ContentType: application/json

body:

      {
         "column_name":"value",
         ...
      }

Json Response:

200 | 201:

    {
        // all resource's fields
    }

400:

    {
        "message": "The given data was invalid.", // or other message
        "errors": {
            "column_name": [
               "The column name field is invalid."
            ],
            ...
        }
    }

The above "errors" are optional and appear only for validation errors while "message" will always be present.

The identifier can be composed by multiple identifiers for pivot resources that have composite primary key (and empty string primary key in their model).
Example:/resources/3_10

Update is not available on some resources.

**UpdateOrCreate** is available on resources that have their model defined with incrementing = false ONLY if the request contains all the keys from the primary key (found also in function getPrimaryKeyFilter).

Update will validate only dirty columns, not all sent columns, meaning the update can be made with all columns of the resource instead of just the changed ones.


#### III.5 Delete resource
**DELETE** /{resource}/{identifier}

**DELETE** /{resource}/{identifier}/{relation}/{relatedIdentifier}

headers:

      Authorization: Bearer ... // if needed. not coded in this lib

Json Response:

204:

    []

400:
    
    {
        "message": ...
    }

Delete is not available by default.
