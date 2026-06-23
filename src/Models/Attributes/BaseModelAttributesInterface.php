<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

use MacropaySolutions\CrufdWizard\Models\BaseModel;

interface BaseModelAttributesInterface
{
    /**
     * @throws \Exception
     * @see BaseModel::$returnNullOnInvalidColumnAttributeAccess
     */
    public function __get(string $key): mixed;

    public function __set(string $key, mixed $value): void;

    public function __isset(string $key): bool;

    public function __unset(string $key): void;

    /**
     * @throws \Throwable
     */
    public function __call(string $method, array $parameters): mixed;
}
