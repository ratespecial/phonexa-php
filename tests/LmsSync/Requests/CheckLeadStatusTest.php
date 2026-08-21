<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync\Requests;

use Ratespecial\Phonexa\LmsSync\Exceptions\LeadNotFoundException;
use Ratespecial\Phonexa\LmsSync\LmsSyncService;
use Ratespecial\Phonexa\LmsSync\Requests\CheckLeadStatus;
use Ratespecial\Phonexa\LmsSync\Responses\CheckLeadStatusResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Ratespecial\Phonexa\AbstractTestCase;

class CheckLeadStatusTest extends AbstractTestCase
{
    public function testEndpointResolution(): void
    {
        $this->assertSame(
            '/lead/check-lead-status',
            (new CheckLeadStatus('W_O0QO'))->resolveEndpoint(),
        );
    }

    public function testSoldLead(): void
    {
        $response = $this->check('check-lead-status-sold');

        $this->assertTrue($response->isSold());
        $this->assertSame('sold', $response->statusText);
        $this->assertStringContainsString('/redirect?id=', (string) $response->redirectUrl);
    }

    public function testInProgressLead(): void
    {
        $response = $this->check('check-lead-status-in-progress');

        $this->assertTrue($response->isInProgress());
        $this->assertFalse($response->isSold());
        $this->assertSame('In Progress', $response->statusText);
        $this->assertNull($response->redirectUrl);
    }

    public function testUnknownLeadThrows(): void
    {
        $this->expectException(LeadNotFoundException::class);
        $this->expectExceptionMessage('Phonexa could not find lead "W_O0QO".');

        $this->check('check-lead-status-not-found');
    }

    public function testCheckKeyIsSentInTheBody(): void
    {
        $mockClient = new MockClient([
            CheckLeadStatus::class => MockResponse::fixture('check-lead-status-sold'),
        ]);

        $this->connector->withMockClient($mockClient);

        (new LmsSyncService($this->connector))->checkLeadStatus('W_O0QO');

        $sent = $mockClient->getLastPendingRequest();

        $this->assertInstanceOf(PendingRequest::class, $sent);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('W_O0QO', $body['checkKey']);
        $this->assertSame($this->env('PHONEXA_LMS_SYNC_API_ID', self::FALLBACK_API_ID), $body['apiId']);
    }

    private function check(string $fixture): CheckLeadStatusResponse
    {
        $mockClient = new MockClient([
            CheckLeadStatus::class => MockResponse::fixture($fixture),
        ]);

        $this->connector->withMockClient($mockClient);

        return (new LmsSyncService($this->connector))->checkLeadStatus('W_O0QO');
    }
}
