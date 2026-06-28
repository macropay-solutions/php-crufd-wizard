<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

use MacropaySolutions\Kernel\Contracts\Support\Jsonable;
use MacropaySolutions\CrufdWizard\Models\BaseModel;

/**
 * For properties autocompletion declare in the children classes (with @ property) all the model's parameters (columns)
 *
 * To avoid declaring them twice, put @ mixin ChildBaseModelFrozenAttributes in ChildBaseModelAttributes
 */
class BaseModelFrozenAttributes implements \Stringable, Jsonable
{
    protected \stdClass $mirror;
    protected array $columns;
    protected bool $escapeWhenCastingToString;
    protected bool $returnNullOnInvalidColumnAttributeAccess;

    public function __construct(BaseModel $ownerBaseModel)
    {
        $this->mirror = (object)\json_decode(\json_encode($ownerBaseModel->attributesToArray()));
        $this->columns = $ownerBaseModel->getColumns();
        $this->returnNullOnInvalidColumnAttributeAccess =
            $ownerBaseModel->shouldReturnNullOnInvalidColumnAttributeAccess();
        $this->escapeWhenCastingToString = $ownerBaseModel->shouldEscapeWhenCastingToString();
    }

    /**
     * @throws \Exception
     * @see static::returnNullOnInvalidColumnAttributeAccess
     */
    public function __get(string $key): mixed
    {
        $result = $this->mirror->{$key} ?? null;

        if ($result instanceof \stdClass) {
            return (object)\json_decode(\json_encode($this->mirror->{$key}));
        }

        if (\is_array($result)) {
            return (array)\json_decode(\json_encode($this->mirror->{$key}));
        }

        if (
            $result !== null
            || $this->returnNullOnInvalidColumnAttributeAccess
            || \in_array($key, $this->columns, true)
        ) {
            return $result;
        }

        try {
            return $this->mirror->{$key};
        } catch (\Throwable $e) {
            throw new \Exception('Undefined attribute: ' . $key . ' in frozen model: ' . static::class);
        }
    }

    /**
     * @throws \Exception
     */
    public function __set(string $key, mixed $value): void
    {
        throw new \Exception('Dynamic properties are forbidden.');
    }

    public function __isset(string $key): bool
    {
        return isset($this->mirror->{$key});
    }

    public function __toString(): string
    {
        return $this->escapeWhenCastingToString ? \e($this->toJson()) : $this->toJson();
    }

    public function toJson($options = 0): string
    {
        return (string)\json_encode($this->mirror, $options);
    }
}
