<?php

namespace MacropaySolutions\CrufdWizard\Helpers;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Support\Str;

class GeneralHelper
{
    public const JSON_RESPONSE_AS_ARRAY_FOR_DECORATION_IN_REQUEST_ATTRIBUTES =
        'jsonResponseAsArrayForDecorationInRequestAttributes';
    public const JSON_RESPONSE_AS_ARRAY = 'jsonResponseAsArray';

    public static function filterDataByKeys(array $data, array $keys): array
    {
        return \array_intersect_key($data, \array_flip($keys));
    }

    public static function getSafeErrorMessage(\Throwable $e, string $messagePrefix = 'apicrud'): string
    {
        $message = \strtolower($e->getMessage());

        if ($e instanceof \PDOException || \str_contains($message, 'sql') || \str_contains($message, 'on line')) {
            \app('log')->error($messagePrefix . ', error: ' . $e->getMessage());

            if (false !== $duplicateEntryPos = \stripos($e->getMessage(), 'Duplicate entry')) {
                return \substr(
                    $e->getMessage(),
                    $duplicateEntryPos,
                    \stripos($e->getMessage(), 'for key') - $duplicateEntryPos - 1
                );
            }

            return 'Something went wrong. Please contact us mentioning current time.';
        }

        if ($e instanceof ModelNotFoundException) {
            return Str::singular($e->getModel()::resourceName()) . ' not found.';
        }

        return $e->getMessage();
    }

    /**
     * @return \Closure|Container|mixed|object|null
     * @throws \MacropaySolutions\Kernel\Contracts\Container\BindingResolutionException
     */
    public static function app(mixed $abstract = null, array $parameters = []): mixed
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }

    public static function isDebug(): bool
    {
        static $isDebug;

        return ($isDebug ??= \function_exists('config') && \config('crufd_wizard.LIVE_MODE') === false)
            && \function_exists('request')
            && null !== \request('sqlDebug');
    }

    /**
     * Backward compatible array_unique($items, SORT_REGULAR) fix
     * see https://github.com/php/php-src/issues/20262#issuecomment-3441217772
     */
    public static function arrayUniqueSortRegular(array $items): array
    {
        $ui = $us = $u = $sp = [];

        foreach ($items as $k => $v) {
            if (\is_string($v)) {
                $us[$v] ??= $k;

                continue;
            }

            if (\is_int($v)) {
                $ui[$v] ??= $k;

                continue;
            }

            if (null === $v) {
                $sp['null'] ??= $k;

                continue;
            }

            if (false === $v) {
                $sp['false'] ??= $k;

                continue;
            }

            if (true === $v) {
                $sp['true'] ??= $k;

                continue;
            }

            $u[$k] = $v;
        }

        return \array_intersect_key(
            $items,
            \array_flip($ui) + \array_flip($us) + \array_flip($sp) + \array_unique($u, SORT_REGULAR)
        );
    }
}
