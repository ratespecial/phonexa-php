<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\ServiceProvider;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Ratespecial\Phonexa\LmsSync\LmsSyncConnector;
use Ratespecial\Phonexa\LmsSync\LmsSyncService;

class PhonexaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        // Check for existence of function.  Lumen doesn't have it.
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/../../config/phonexa.php' => config_path('phonexa.php'),
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/phonexa.php', 'phonexa');

        $this->app->bind(LmsSyncConnector::class, function (Container $app) {
            $config = $app['config']['phonexa']['lms-sync'];

            $connector = new LmsSyncConnector(
                baseUrl: (string) $config['base-url'],
                apiId: (string) $config['api-id'],
                apiPassword: (string) $config['api-password'],
            );

            $connector->applicationName = (string) $app['config']['app.name'];
            $connector->testMode        = (bool) $config['test-mode'];

            if (! empty($config['product-id'])) {
                $connector->defaultProductId = (int) $config['product-id'];
            }

            return $connector;
        });

        $this->app->bind(
            LmsSyncService::class,
            fn (Container $app) => new LmsSyncService($app->make(LmsSyncConnector::class)),
        );
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return [
            LmsSyncConnector::class,
            LmsSyncService::class,
        ];
    }
}
