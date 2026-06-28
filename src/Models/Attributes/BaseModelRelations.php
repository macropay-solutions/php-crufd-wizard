<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

use MacropaySolutions\Kernel\Database\Obvious\Relations\Relation;

/**
 * For properties autocompletion declare in the children classes (with @ property-read) all the model's relations
 */
class BaseModelRelations extends AbstractBaseModelAttributes implements BaseModelAttributesInterface
{
    /**
     * @inheritdoc
     */
    public function __get(string $key): mixed
    {
        return $this->ownerRef->get()?->getRelationValue($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->ownerRef->get()?->setRelation($key, $value);
    }

    public function __isset(string $key): bool
    {
        return (bool)$this->ownerRef->get()?->relationLoaded($key);
    }

    public function __unset(string $key): void
    {
        $this->ownerRef->get()?->unsetRelation($key);
    }

    public function __call(string $method, array $parameters): Relation
    {
        return $this->ownerRef->get()?->callSegregatedRelation($method, $parameters);
    }
}
