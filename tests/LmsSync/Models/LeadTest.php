<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync\Models;

use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\LmsSync\Contracts\ProvidesLeadData;
use Ratespecial\Phonexa\LmsSync\Models\Lead;

class LeadTest extends TestCase
{
    public function testImplementsTheContract(): void
    {
        $this->assertInstanceOf(ProvidesLeadData::class, new Lead());
    }

    public function testAdHocFieldsAreSetAndReadThroughMagicAccessors(): void
    {
        $lead = new Lead();

        $lead->firstName     = 'John';
        $lead->unsecuredDebt = 10000;

        $this->assertSame('John', $lead->firstName);
        $this->assertSame(10000, $lead->unsecuredDebt);
        $this->assertTrue(isset($lead->firstName));
        $this->assertFalse(isset($lead->nothingHere));
        $this->assertNull($lead->nothingHere);
    }

    public function testSystemFieldsStayOnTypedProperties(): void
    {
        $lead = new Lead();

        $lead->apiId       = 'API_ID';
        $lead->apiPassword = 'secret';
        $lead->productId   = 218;

        // Typed properties are never routed into the ad-hoc bag.
        $this->assertFalse($lead->has('apiId'));
        $this->assertFalse($lead->has('productId'));
        $this->assertSame(218, $lead->productId);
    }

    public function testFillRoutesSystemFieldsAndCastsThem(): void
    {
        $lead = new Lead([
            'apiId'     => 'API_ID',
            'productId' => '218',
            'tPar'      => ['affiliateId' => '123'],
            'firstName' => 'John',
        ]);

        $this->assertSame('API_ID', $lead->apiId);
        $this->assertSame(218, $lead->productId);
        $this->assertSame(['affiliateId' => '123'], $lead->tPar);
        $this->assertSame('John', $lead->get('firstName'));
    }

    public function testSetGetHasAndForget(): void
    {
        $lead = new Lead();

        $lead->set('email', 'john.n@yahoo.com');

        $this->assertTrue($lead->has('email'));
        $this->assertSame('john.n@yahoo.com', $lead->get('email'));
        $this->assertSame('fallback', $lead->get('missing', 'fallback'));

        $lead->forget('email');

        $this->assertFalse($lead->has('email'));
    }

    public function testSetTParAccumulates(): void
    {
        $lead = new Lead();

        $lead->setTPar('affiliateId', '123')
            ->setTPar('affiliateSubId', '123_444');

        $this->assertSame(
            ['affiliateId' => '123', 'affiliateSubId' => '123_444'],
            $lead->tPar,
        );
    }

    public function testGetLeadDataMergesSystemAndAdHocFields(): void
    {
        $lead = new Lead([
            'productId' => 218,
            'price'     => 0.01,
            'firstName' => 'John',
            'lastName'  => 'Smith',
        ]);

        $lead->setTPar('affiliateId', '123');

        $this->assertSame([
            'price'     => 0.01,
            'firstName' => 'John',
            'lastName'  => 'Smith',
            'productId' => 218,
            'tPar'      => ['affiliateId' => '123'],
        ], $lead->getLeadData());
    }

    public function testGetLeadDataOmitsUnsetSystemFields(): void
    {
        $data = (new Lead(['firstName' => 'John']))->getLeadData();

        $this->assertSame(['firstName' => 'John'], $data);
        $this->assertArrayNotHasKey('apiId', $data);
        $this->assertArrayNotHasKey('apiPassword', $data);
        $this->assertArrayNotHasKey('productId', $data);
        $this->assertArrayNotHasKey('tPar', $data);
    }

    public function testCredentialsAreIncludedWhenOverriddenOnTheLead(): void
    {
        $lead = new Lead();

        $lead->apiId       = 'OTHER_ACCOUNT';
        $lead->apiPassword = 'other-secret';

        $data = $lead->getLeadData();

        $this->assertSame('OTHER_ACCOUNT', $data['apiId']);
        $this->assertSame('other-secret', $data['apiPassword']);
    }

    public function testFieldNamesArePreservedVerbatimBecausePhonexaIsCaseSensitive(): void
    {
        $lead = new Lead(['consentEmailSms' => 'YES', 'incomeNetMonthly' => 5000]);

        $this->assertSame(
            ['consentEmailSms', 'incomeNetMonthly'],
            array_keys($lead->getLeadData()),
        );
    }
}
