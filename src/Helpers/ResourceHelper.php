<?php

namespace MacropaySolutions\CrufdWizard\Helpers;

use MacropaySolutions\CrufdWizard\Models\BaseModel;

class ResourceHelper
{
    /**
     * @param array $modelFqnToControllerMap
     *     [BaseModelChild::class => ResourceControllerChild::class]
     * @throws \Throwable
     */
    public static function getResourceNameToControllerFQNMap(array $modelFqnToControllerMap): array
    {
        static $resourceNameToControllerFQNMap;

        if (isset($resourceNameToControllerFQNMap)) {
            return $resourceNameToControllerFQNMap;
        }

        $map = [];

        foreach ($modelFqnToControllerMap as $resourceFQN => $controllerFQN) {
            /** @var BaseModel $resourceFQN */
            $resource = $resourceFQN::resourceName();

            if (isset($map[$resource])) {
                throw new \Exception('Duplicate resource name: ' . $resource);
            }

            if (!\class_exists($controllerFQN)) {
                throw new \Exception('Controller class does not exist for resource: ' . $resource);
            }

            $map[$resource] = $controllerFQN;
        }

        return $resourceNameToControllerFQNMap = $map;
    }

    /**
     * @param array $modelFqnToControllerMap
     *     [BaseModelChild::class => ResourceControllerChild::class]
     */
    public static function getResourceControllerToModelFQNMap(array $modelFqnToControllerMap): array
    {
        static $resourceControllerToModelFQNMap;

        if (isset($resourceControllerToModelFQNMap)) {
            return $resourceControllerToModelFQNMap;
        }

        return $resourceControllerToModelFQNMap = \array_flip($modelFqnToControllerMap);
    }
}
