<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

use MacropaySolutions\CrufdWizard\Models\BaseModel;

abstract class BaseModelLazyAttributes
{
    protected const A = 'a';
    protected const R = 'r';
    protected static string $attributeType = self::A;
    /**
     * @var array<string, <string, string>>
     */
    protected static array $activeRecordFqnSegregationPropertiesFqnsMap = [];

    public static function getAbstractBaseModelAttributes(\WeakReference $ownerRef): AbstractBaseModelAttributes
    {
        /** @var BaseModel $owner */
        if (!($owner = $ownerRef->get()) instanceof BaseModel) {
            throw new \Exception('WeakReference lost');
        }

        $class = $owner::class;

        if (static::$attributeType === self::A && isset($owner::$baseModelAttributesFqn)) {
            return new $owner::$baseModelAttributesFqn($ownerRef);
        }

        if (static::$attributeType === self::R && isset($owner::$baseModelRelationsFqn)) {
            return new $owner::$baseModelRelationsFqn($ownerRef);
        }

        if (isset(self::$activeRecordFqnSegregationPropertiesFqnsMap[$class][static::$attributeType])) {
            return
                new self::$activeRecordFqnSegregationPropertiesFqnsMap[$class][static::$attributeType]($ownerRef);
        }

        $prefix = \substr($class, 0, $l = (-1 * (\strlen($class) - (int)\strrpos($class, '\\') - 1))) .
            'Attributes\\' . \substr($class, $l);
        $postfix = [self::A => 'Attributes', self::R => 'Relations'][static::$attributeType];

        $return = new (
            \class_exists($classFqn = $prefix . $postfix) ?
                $classFqn :
                __NAMESPACE__ . '\BaseModel' . $postfix
        )($ownerRef);

        self::$activeRecordFqnSegregationPropertiesFqnsMap[$class][static::$attributeType] = $return::class;

        return $return;
    }
}
