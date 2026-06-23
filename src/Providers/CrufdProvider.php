<?php

namespace MacropaySolutions\CrufdWizard\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\ServiceProvider;
use MacropaySolutions\CrufdWizard\Helpers\GeneralHelper;
use MacropaySolutions\CrufdWizard\Responses\DecoratableJsonResponse;

class CrufdProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/crufd_wizard.php', 'crufd_wizard');

        $this->app->bind(JsonResponse::class, function ($app, $parameters): JsonResponse {
            if (
                false === ($parameters['json'] ?? $parameters[4] ?? false)
                && \in_array($code =
                    (string)($parameters['status'] ?? $parameters[1] ?? '200'), ['200', '201', '202'], true)
                && \is_string(
                    $decoratorFlag = ($request = $app['request'])->header(
                        GeneralHelper::JSON_RESPONSE_AS_ARRAY_FOR_DECORATION_IN_REQUEST_ATTRIBUTES
                    )
                )
                && '' !== (string)($appKey = $app['config']->get('app.key'))
                && \hash_equals(
                    $decoratorFlag,
                    \hash_hmac('sha256', GeneralHelper::JSON_RESPONSE_AS_ARRAY, $appKey)
                )
            ) {
                if (\is_array($parameters['data'] ?? null)) {
                    $request->attributes->set(GeneralHelper::JSON_RESPONSE_AS_ARRAY, $parameters['data']);

                    return new DecoratableJsonResponse(
                        [],
                        $code,
                        $parameters['headers'] ?? [],
                        $parameters['options'] ?? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        false
                    );
                }

                if (\is_array($parameters[0] ?? null)) {
                    $request->attributes->set(GeneralHelper::JSON_RESPONSE_AS_ARRAY, $parameters[0]);

                    return new DecoratableJsonResponse(
                        [],
                        $code,
                        $parameters[2] ?? [],
                        $parameters[3] ?? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        false
                    );
                }
            }

            return new JsonResponse(
                $parameters['data'] ?? $parameters[0] ?? null,
                $parameters['status'] ?? $parameters[1] ?? 200,
                $parameters['headers'] ?? $parameters[2] ?? [],
                $parameters['options'] ?? $parameters[3] ?? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                $parameters['json'] ?? $parameters[4] ?? false,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/crufd_wizard.php' => \function_exists('config_path') ?
                    \config_path('crufd_wizard.php') :
                    \base_path('config/crufd_wizard.php')
            ], 'config');
        }
    }
}
