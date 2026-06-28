<?php

namespace MacropaySolutions\CrufdWizard\Responses;

use MacropaySolutions\CrufdWizard\Helpers\GeneralHelper;
use MacropaySolutions\Kernel\Http\JsonResponse;

class DecoratableJsonResponse extends JsonResponse
{
    public function getData($assoc = false, $depth = 512)
    {
        if ($assoc) {
            return (array)(\request()->attributes->get(GeneralHelper::JSON_RESPONSE_AS_ARRAY) ??
                parent::getData(true, $depth));
        }

        if (\is_array($returnAsArray = \request()->attributes->get(GeneralHelper::JSON_RESPONSE_AS_ARRAY) ?? null)) {
            return \json_decode(\json_encode($returnAsArray, 0, $depth), null, $depth);
        }

        return parent::getData(false, $depth);
    }
}