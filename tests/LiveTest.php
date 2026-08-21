<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa;

use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\LmsSync\Contracts\ProvidesLeadData;
use Ratespecial\Phonexa\LmsSync\LmsSyncConnector;
use Ratespecial\Phonexa\LmsSync\LmsSyncService;
use Ratespecial\Phonexa\LmsSync\Models\Lead;

/**
 * Integration checks against a real Phonexa instance.
 *
 * Excluded from phpunit.xml — run it by hand once tests/.env holds live credentials:
 *
 *     ./vendor/bin/phpunit tests/LiveTest.php
 *
 * Every lead posted here sets testMode=1, so nothing is ever sold for real. Tests call the
 * service rather than the connector, because the service is what consuming apps use.
 */
class LiveTest extends TestCase
{
    private LmsSyncService $service;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $baseUrl     = (string) getenv('PHONEXA_LMS_SYNC_BASE_URL');
        $apiId       = (string) getenv('PHONEXA_LMS_SYNC_API_ID');
        $apiPassword = (string) getenv('PHONEXA_LMS_SYNC_API_PASSWORD');

        if ($baseUrl === '' || $apiId === '' || $apiPassword === '') {
            $this->markTestSkipped('No live credentials in tests/.env, skipping live tests');
        }

        $connector = new LmsSyncConnector($baseUrl, $apiId, $apiPassword);

        // Belt and braces: the connector flag AND the field on every lead below.
        $connector->testMode = true;

        $this->service   = new LmsSyncService($connector);
        $this->productId = (int) getenv('PHONEXA_LMS_SYNC_PRODUCT_ID');
    }

    public function testPostLead(): void
    {
        $response = $this->service->postLead($this->testLead());

        print_r($response);

        $this->assertNotSame(0, $response->status);
    }

    public function testPostLeadForcedSold(): void
    {
        $lead           = $this->testLead();
        $lead->testSold = 1;

        $response = $this->service->postLead($lead);

        print_r($response);

        $this->assertTrue($response->isSold());
        $this->assertNotNull($response->leadId);
        $this->assertNotNull($response->redirectUrl);
    }

    public function testPostLeadThenCheckStatus(): void
    {
        $lead           = $this->testLead();
        $lead->testSold = 1;

        $posted = $this->service->postLead($lead);

        $this->assertNotNull($posted->leadId);

        $status = $this->service->checkLeadStatus($posted->leadId);

        print_r($status);

        $this->assertNotSame(0, $status->status);
    }

    /**
     * The library couples to the ProvidesLeadData interface and nothing else, so an object
     * that merely implements it — no Lead subclassing involved — must post just as well.
     */
    public function testPostArbitraryObjectImplementingTheContract(): void
    {
        $productId = $this->productId;

        $lead = new class($productId) implements ProvidesLeadData
        {
            public function __construct(private readonly int $productId) {}

            public function getLeadData(): array
            {
                return [
                    'productId'     => $this->productId,
                    'price'         => 0.01,
                    'firstName'     => 'John',
                    'lastName'      => 'Smith',
                    'email'         => 'john.n@yahoo.com',
                    'cellPhone'     => '5555555555',
                    'address'       => 'my address',
                    'zip'           => '90210',
                    'dob'           => '1980-10-21',
                    'unsecuredDebt' => 10000,
                    'testMode'      => 1,
                    'testSold'      => 1,
                ];
            }
        };

        $response = $this->service->postLead($lead);

        print_r($response);

        $this->assertTrue($response->isSold());
    }

    private function testLead(): Lead
    {
        return new Lead([
            'productId'     => $this->productId,
            'price'         => 0.01,
            'firstName'     => 'John',
            'lastName'      => 'Smith',
            'email'         => 'john.n@yahoo.com',
            'cellPhone'     => '5555555555',
            'address'       => 'my address',
            'city'          => 'Glendale',
            'state'         => 'CA',
            'zip'           => '90210',
            'dob'           => '1980-10-21',
            'unsecuredDebt' => 10000,
            'testMode'      => 1,
        ]);
    }
}
