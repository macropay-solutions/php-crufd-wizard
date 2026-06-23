<?php

namespace MacropaySolutions\CrufdWizard\Models\Attributes;

abstract class BaseModelLazyRelations extends BaseModelLazyAttributes
{
    protected static string $attributeType = self::R;
}
