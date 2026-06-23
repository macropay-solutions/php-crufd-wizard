<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

abstract class AbstractBaseModelAttributes
{
    public function __construct(protected \WeakReference $ownerRef)
    {
    }

    /**
     * @throws \Throwable
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->ownerRef->get()?->$method(...$parameters);
    }
}
