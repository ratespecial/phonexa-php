<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync\Requests;

use Ratespecial\Phonexa\LmsSync\Exceptions\DuplicateLeadException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadValidationException;
use Ratespecial\Phonexa\LmsSync\LmsSyncService;
use Ratespecial\Phonexa\LmsSync\Models\Lead;
use Ratespecial\Phonexa\LmsSync\Requests\PostLead;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Ratespecial\Phonexa\AbstractTestCase;

class PostLeadTest extends AbstractTestCase
{
    public function testEndpointResolution(): void
    {
        $this->assertSame('/lead/', (new PostLead(new Lead()))->resolveEndpoint());
    }

    public function testSoldLead(): void
    {
        $mockClient = new MockClient([
            PostLead::class => MockResponse::fixture('post-lead-sold'),
        ]);

        $this->connector->withMockClient($mockClient);

        $service  = new LmsSyncService($this->connector);
        $response = $service->postLead($this->debtLead());

        $this->assertTrue($response->isSold());
        $this->assertFalse($response->isRejected());
        $this->assertSame(1, $response->status);
        $this->assertSame('sold', $response->statusText);
        $this->assertSame('W_O0QO', $response->leadId);
        $this->assertSame(1.0, $response->price);
        $this->assertStringContainsString('/redirect?id=', (string) $response->redirectUrl);
    }

    public function testRejectedLeadReturnsADtoRatherThanThrowing(): void
    {
        $mockClient = new MockClient([
            PostLead::class => MockResponse::fixture('post-lead-reject'),
        ]);

        $this->connector->withMockClient($mockClient);

        $service  = new LmsSyncService($this->connector);
        $response = $service->postLead($this->debtLead());

        $this->assertTrue($response->isRejected());
        $this->assertSame('reject', $response->statusText);
        $this->assertSame('23', $response->leadId);
        $this->assertNull($response->price);
        $this->assertNull($response->redirectUrl);
    }

    public function testValidationErrorThrowsWithParsedErrors(): void
    {
        $mockClient = new MockClient([
            PostLead::class => MockResponse::fixture('post-lead-error'),
        ]);

        $this->connector->withMockClient($mockClient);

        $service = new LmsSyncService($this->connector);

        try {
            $service->postLead(new Lead());
            $this->fail('Expected a LeadValidationException');
        } catch (LeadValidationException $e) {
            $this->assertSame(
                'email: required, zip: required',
                $e->getMessage(),
            );
            $this->assertSame(
                [['email' => 'required'], ['zip' => 'required']],
                $e->getErrors(),
            );
            $this->assertSame(200, $e->getResponse()->status());
        }
    }

    public function testDuplicateApplicationThrowsTheDuplicateSubclass(): void
    {
        $mockClient = new MockClient([
            PostLead::class => MockResponse::fixture('post-lead-duplicate'),
        ]);

        $this->connector->withMockClient($mockClient);

        $service = new LmsSyncService($this->connector);

        try {
            $service->postLead($this->debtLead());
            $this->fail('Expected a DuplicateLeadException');
        } catch (DuplicateLeadException $e) {
            $this->assertInstanceOf(LeadValidationException::class, $e);
            $this->assertSame(
                'Duplicate Application: Duplicate Application',
                $e->getMessage(),
            );
            $this->assertSame(
                [['Duplicate Application' => 'Duplicate Application']],
                $e->getErrors(),
            );
            $this->assertSame(200, $e->getResponse()->status());
        }
    }

    public function testConnectorCredentialsAreInjectedIntoTheBody(): void
    {
        $body = $this->sentBody(new Lead(['firstName' => 'John']));

        $this->assertSame($this->env('PHONEXA_LMS_SYNC_API_ID', self::FALLBACK_API_ID), $body['apiId']);
        $this->assertSame($this->env('PHONEXA_LMS_SYNC_API_PASSWORD', self::FALLBACK_API_PASSWORD), $body['apiPassword']);
        $this->assertSame('John', $body['firstName']);
    }

    public function testLeadSuppliedCredentialsWinOverTheConnector(): void
    {
        $lead = new Lead();

        $lead->apiId       = 'OTHER_ACCOUNT';
        $lead->apiPassword = 'other-secret';

        $body = $this->sentBody($lead);

        $this->assertSame('OTHER_ACCOUNT', $body['apiId']);
        $this->assertSame('other-secret', $body['apiPassword']);
    }

    public function testTParIsNestedRatherThanBracketEncoded(): void
    {
        $lead = new Lead();
        $lead->setTPar('affiliateId', '123');
        $lead->setTPar('affiliateSubId', '123_444');

        $body = $this->sentBody($lead);

        $this->assertSame(
            ['affiliateId' => '123', 'affiliateSubId' => '123_444'],
            $body['tPar'],
        );
    }

    /**
     * Send a lead against the sold fixture and return the decoded request body.
     *
     * @return array<string, mixed>
     */
    private function sentBody(Lead $lead): array
    {
        $mockClient = new MockClient([
            PostLead::class => MockResponse::fixture('post-lead-sold'),
        ]);

        $this->connector->withMockClient($mockClient);

        (new LmsSyncService($this->connector))->postLead($lead);

        $sent = $mockClient->getLastPendingRequest();

        $this->assertInstanceOf(PendingRequest::class, $sent);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $sent->body(), true, 512, JSON_THROW_ON_ERROR);

        return $body;
    }

    private function debtLead(): Lead
    {
        return new Lead([
            'productId'     => 218,
            'price'         => 0.01,
            'firstName'     => 'John',
            'lastName'      => 'Smith',
            'email'         => 'john.n@yahoo.com',
            'cellPhone'     => '5555555555',
            'address'       => 'my address',
            'zip'           => '90210',
            'dob'           => '1980-10-21',
            'unsecuredDebt' => 10000,
        ]);
    }
}
