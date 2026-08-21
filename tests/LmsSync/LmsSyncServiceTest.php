<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync;

use Ratespecial\Phonexa\LmsSync\Exceptions\LeadStatusTimeoutException;
use Ratespecial\Phonexa\LmsSync\LmsSyncService;
use Ratespecial\Phonexa\LmsSync\Requests\CheckLeadStatus;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Ratespecial\Phonexa\AbstractTestCase;

class LmsSyncServiceTest extends AbstractTestCase
{
    /**
     * @var int[] Seconds each simulated sleep was asked to wait.
     */
    private array $slept = [];

    /**
     * @var float Simulated clock, advanced only by the fake sleeper.
     */
    private float $time = 0.0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slept = [];
        $this->time  = 0.0;
    }

    public function testWaitReturnsAsSoonAsTheAuctionSettles(): void
    {
        $service = $this->serviceReturning(
            'check-lead-status-in-progress',
            'check-lead-status-in-progress',
            'check-lead-status-sold',
        );

        $response = $service->waitForLeadStatus('W_O0QO');

        $this->assertTrue($response->isSold());
        $this->assertSame([1, 1], $this->slept);
    }

    public function testWaitReturnsImmediatelyWhenAlreadySettled(): void
    {
        $service = $this->serviceReturning('check-lead-status-sold');

        $response = $service->waitForLeadStatus('W_O0QO');

        $this->assertTrue($response->isSold());
        $this->assertSame([], $this->slept, 'A settled lead should never sleep');
    }

    public function testWaitFollowsPhonexasDocumentedBackoff(): void
    {
        // Always in progress, so it polls until the 120s deadline.
        $service = $this->serviceReturning(
            ...array_fill(0, 60, 'check-lead-status-in-progress'),
        );

        try {
            $service->waitForLeadStatus('W_O0QO', 120);
            $this->fail('Expected a LeadStatusTimeoutException');
        } catch (LeadStatusTimeoutException $e) {
            $this->assertSame('W_O0QO', $e->getCheckKey());
            $this->assertSame(120, $e->getTimeout());
        }

        // 1s while elapsed <= 10, 2s to 30, 3s to 60, 5s beyond.
        // Elapsed 0 through 10 inclusive all sleep 1s, so there are 11 of them.
        $this->assertSame(array_fill(0, 11, 1), array_slice($this->slept, 0, 11));
        $this->assertSame(2, $this->slept[11], 'Should widen to 2s once past 10 seconds');
        $this->assertContains(3, $this->slept, 'Should widen to 3s after 30 seconds');
        $this->assertContains(5, $this->slept, 'Should widen to 5s after 60 seconds');

        // Never sleeps past the deadline.
        $this->assertLessThanOrEqual(120.0, $this->time);
    }

    public function testWaitNeverSleepsPastTheDeadline(): void
    {
        $service = $this->serviceReturning(
            ...array_fill(0, 20, 'check-lead-status-in-progress'),
        );

        $this->expectException(LeadStatusTimeoutException::class);

        try {
            $service->waitForLeadStatus('W_O0QO', 5);
        } finally {
            $this->assertSame(5.0, $this->time);
            $this->assertSame([1, 1, 1, 1, 1], $this->slept);
        }
    }

    /**
     * Build a service whose CheckLeadStatus calls answer with the given fixtures in order,
     * driving a simulated clock so no test actually sleeps.
     */
    private function serviceReturning(string ...$fixtures): LmsSyncService
    {
        $mockClient = new MockClient(array_map(
            static fn (string $fixture) => MockResponse::fixture($fixture),
            $fixtures,
        ));

        $this->connector->withMockClient($mockClient);

        $service = new LmsSyncService($this->connector);

        $service->clock   = fn (): float => $this->time;
        $service->sleeper = function (int $seconds): void {
            $this->slept[] = $seconds;
            $this->time += $seconds;
        };

        return $service;
    }

    public function testRequestClassIsTheOneBeingMocked(): void
    {
        $this->assertSame(
            '/lead/check-lead-status',
            (new CheckLeadStatus('x'))->resolveEndpoint(),
        );
    }
}
