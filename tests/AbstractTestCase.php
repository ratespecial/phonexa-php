<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa;

use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\LmsSync\LmsSyncConnector;
use Saloon\Config;
use Saloon\MockConfig;

abstract class AbstractTestCase extends TestCase
{
    /**
     * @var string Stand-in credentials so the mocked suite runs without a tests/.env.
     */
    protected const string FALLBACK_BASE_URL = 'https://leads-inst1-client.phonexa.test';

    protected const string FALLBACK_API_ID = 'TEST_API_ID';

    protected const string FALLBACK_API_PASSWORD = 'test-api-password';

    protected LmsSyncConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test in this suite is mocked, so it must not depend on live credentials
        // being present. LiveTest reads the same variables and skips itself when they
        // are missing, which is the only place real values matter.
        $this->connector = new LmsSyncConnector(
            baseUrl: $this->env('PHONEXA_LMS_SYNC_BASE_URL', self::FALLBACK_BASE_URL),
            apiId: $this->env('PHONEXA_LMS_SYNC_API_ID', self::FALLBACK_API_ID),
            apiPassword: $this->env('PHONEXA_LMS_SYNC_API_PASSWORD', self::FALLBACK_API_PASSWORD),
        );

        $this->connector->retryInterval = 0;

        MockConfig::setFixturePath(TEST_ROOT . '/fixtures');

        // Don't allow requests without a MockClient.  https://docs.saloon.dev/the-basics/testing#preventing-stray-requests
        Config::preventStrayRequests();
    }

    protected function env(string $key, string $default): string
    {
        $value = (string) getenv($key);

        return $value !== '' ? $value : $default;
    }
}
