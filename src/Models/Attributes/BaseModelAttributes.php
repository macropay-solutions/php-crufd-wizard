<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

/**
 * For properties autocompletion declare in the children classes (with @ property) all the model's parameters (columns)
 */
class BaseModelAttributes extends AbstractBaseModelAttributes implements BaseModelAttributesInterface
{
    /**
     * @inheritdoc
     */
    public function __get(string $key): mixed
    {
        return $this->ownerRef->get()?->getAttributeValue($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->ownerRef->get()?->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return (bool)$this->ownerRef->get()?->attributeOffsetExists($key);
    }

    public function __unset(string $key): void
    {
        $this->ownerRef->get()?->attributeOffsetUnset($key);
    }
}
