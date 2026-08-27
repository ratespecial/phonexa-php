<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa;

use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\Exceptions\PhonexaException;
use Ratespecial\Phonexa\LmsSync\LmsSyncConnector;
use Ratespecial\Phonexa\LmsSync\Models\Lead;
use Ratespecial\Phonexa\LmsSync\Requests\CheckLeadStatus;
use Ratespecial\Phonexa\LmsSync\Requests\PostLead;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

class LmsSyncConnectorTest extends TestCase
{
    private MockClient $mock;

    protected function setUp(): void
    {
        parent::setUp();

        MockClient::destroyGlobal();
    }

    private function makeConnector(MockClient $mockClient): LmsSyncConnector
    {
        $connector                = new LmsSyncConnector('https://leads.phonexa.test', 'api-id', 'api-password');
        $connector->retryInterval = 0;
        $connector->withMockClient($mockClient);

        return $connector;
    }

    public function testStatusCheckRetriesA500ThenSucceeds(): void
    {
        $mock = new MockClient([
            MockResponse::make('', 500),
            MockResponse::make(['status' => 1, 'status_text' => 'sold'], 200),
        ]);

        $response = $this->makeConnector($mock)->send(new CheckLeadStatus('W_O0QO'));

        $this->assertSame(200, $response->status());
        $mock->assertSentCount(2);
    }

    public function testStatusCheckDoesNotRetry4xx(): void
    {
        $mock = new MockClient([
            MockResponse::make(['error' => 'bad'], 400),
        ]);

        try {
            $this->makeConnector($mock)->send(new CheckLeadStatus('W_O0QO'));
            $this->fail('Expected a PhonexaException');
        } catch (PhonexaException $e) {
            $this->assertSame(400, $e->getResponse()->status());
        }

        $mock->assertSentCount(1);
    }

    public function testStatusCheckExhaustsAllRetries(): void
    {
        $mock = new MockClient([
            MockResponse::make('', 500),
            MockResponse::make('', 500),
            MockResponse::make('', 500),
        ]);

        $this->expectException(PhonexaException::class);

        try {
            $this->makeConnector($mock)->send(new CheckLeadStatus('W_O0QO'));
        } finally {
            $mock->assertSentCount(3);
        }
    }

    /**
     * Phonexa has no idempotency key, so a replayed lead POST could be sold twice.
     */
    public function testPostingALeadIsNeverRetried(): void
    {
        $mock = new MockClient([
            MockResponse::make('', 500),
            MockResponse::make(['status' => 1, 'status_text' => 'sold'], 200),
        ]);

        $this->expectException(PhonexaException::class);

        try {
            $this->makeConnector($mock)->send(new PostLead(new Lead()));
        } finally {
            $mock->assertSentCount(1);
        }
    }

    public function testTestModeIsAppliedToPostedLeads(): void
    {
        $connector           = $this->connectorWithSoldMock();
        $connector->testMode = true;

        $connector->send(new PostLead(new Lead(['firstName' => 'John'])));

        $this->assertSame(1, $this->sentBody()['testMode']);
    }

    public function testTestModeIsNotAppliedByDefault(): void
    {
        $connector = $this->connectorWithSoldMock();

        $connector->send(new PostLead(new Lead(['firstName' => 'John'])));

        $this->assertArrayNotHasKey('testMode', $this->sentBody());
    }

    public function testTestModeIsNotAppliedToStatusChecks(): void
    {
        $connector           = $this->connectorWithSoldMock();
        $connector->testMode = true;

        $connector->send(new CheckLeadStatus('W_O0QO'));

        $this->assertArrayNotHasKey('testMode', $this->sentBody());
    }

    public function testDefaultProductIdFillsInWhenTheLeadHasNone(): void
    {
        $connector                   = $this->connectorWithSoldMock();
        $connector->defaultProductId = 218;

        $connector->send(new PostLead(new Lead(['firstName' => 'John'])));

        $this->assertSame(218, $this->sentBody()['productId']);
    }

    public function testLeadProductIdWinsOverTheConnectorDefault(): void
    {
        $connector                   = $this->connectorWithSoldMock();
        $connector->defaultProductId = 218;

        $connector->send(new PostLead(new Lead(['productId' => 999])));

        $this->assertSame(999, $this->sentBody()['productId']);
    }

    public function testUserAgentCarriesTheApplicationName(): void
    {
        $connector                  = $this->connectorWithSoldMock();
        $connector->applicationName = 'MyApp';

        $connector->send(new CheckLeadStatus('W_O0QO'));

        $sent = $this->mock->getLastPendingRequest();

        $this->assertInstanceOf(PendingRequest::class, $sent);
        $this->assertSame('lib.phonexa (MyApp)', $sent->headers()->get('User-Agent'));
    }

    public function testMissingCredentialsAreRejected(): void
    {
        $connector = new LmsSyncConnector('https://leads.phonexa.test', '', '');
        $connector->withMockClient(new MockClient([MockResponse::make([], 200)]));

        $this->expectExceptionMessage('An API ID and API password are required');

        $connector->send(new CheckLeadStatus('W_O0QO'));
    }

    public function testLeadCredentialsAreUsedWhenTheConnectorHasNone(): void
    {
        $this->mock = new MockClient([
            MockResponse::make(['status' => 1, 'status_text' => 'sold'], 200),
        ]);

        $connector = new LmsSyncConnector('https://leads.phonexa.test', '', '');
        $connector->withMockClient($this->mock);

        $lead              = new Lead();
        $lead->apiId       = 'LEAD_ACCOUNT';
        $lead->apiPassword = 'lead-secret';

        $connector->send(new PostLead($lead));

        $body = $this->sentBody();

        $this->assertSame('LEAD_ACCOUNT', $body['apiId']);
        $this->assertSame('lead-secret', $body['apiPassword']);
    }

    public function testLeadSuppliesOnlyTheHalfTheConnectorIsMissing(): void
    {
        $this->mock = new MockClient([
            MockResponse::make(['status' => 1, 'status_text' => 'sold'], 200),
        ]);

        $connector = new LmsSyncConnector('https://leads.phonexa.test', 'api-id', '');
        $connector->withMockClient($this->mock);

        $lead              = new Lead();
        $lead->apiPassword = 'lead-secret';

        $connector->send(new PostLead($lead));

        $body = $this->sentBody();

        $this->assertSame('api-id', $body['apiId']);
        $this->assertSame('lead-secret', $body['apiPassword']);
    }

    public function testALeadWithHalfACredentialPairIsStillRejected(): void
    {
        $connector = new LmsSyncConnector('https://leads.phonexa.test', '', '');
        $connector->withMockClient(new MockClient([MockResponse::make([], 200)]));

        $lead        = new Lead();
        $lead->apiId = 'LEAD_ACCOUNT';

        $this->expectExceptionMessage('An API ID and API password are required');

        $connector->send(new PostLead($lead));
    }

    private function connectorWithSoldMock(): LmsSyncConnector
    {
        $this->mock = new MockClient([
            MockResponse::make(['status' => 1, 'status_text' => 'sold'], 200),
        ]);

        return $this->makeConnector($this->mock);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentBody(): array
    {
        $sent = $this->mock->getLastPendingRequest();

        $this->assertInstanceOf(PendingRequest::class, $sent);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->body(), true, 512, JSON_THROW_ON_ERROR);

        return $body;
    }
}
