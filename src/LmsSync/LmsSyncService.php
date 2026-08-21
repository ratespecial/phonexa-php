<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync;

use Closure;
use Ratespecial\Phonexa\LmsSync\Contracts\ProvidesLeadData;
use Ratespecial\Phonexa\LmsSync\Exceptions\DuplicateLeadException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadNotFoundException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadStatusTimeoutException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadValidationException;
use Ratespecial\Phonexa\LmsSync\Requests\CheckLeadStatus;
use Ratespecial\Phonexa\LmsSync\Requests\PostLead;
use Ratespecial\Phonexa\LmsSync\Responses\CheckLeadStatusResponse;
use Ratespecial\Phonexa\LmsSync\Responses\PostLeadResponse;

/**
 * High-level service for the Phonexa LMS Sync API.
 */
class LmsSyncService
{
    /**
     * @var Closure|null Receives an int number of seconds. Overridden in tests so polling is instant.
     */
    public ?Closure $sleeper = null;

    /**
     * @var Closure|null Returns the current time as a float of seconds. Overridden in tests.
     */
    public ?Closure $clock = null;

    public function __construct(public readonly LmsSyncConnector $connector) {}

    /**
     * Post a lead into Phonexa to be sold.
     *
     * Returns for sold, reject and in-progress alike — a reject means no buyer wanted the
     * lead, which is an ordinary outcome rather than a failure.
     *
     * @throws DuplicateLeadException When the lead has already been submitted.
     * @throws LeadValidationException When Phonexa reports a validation or authorisation failure.
     */
    public function postLead(ProvidesLeadData $lead): PostLeadResponse
    {
        $req = new PostLead($lead);

        $resp = $this->connector->send($req);

        return $resp->dto();
    }

    /**
     * Look up what became of a previously posted lead.
     *
     * @param  string  $checkKey  The `lead_id` from the PostLeadResponse.
     *
     * @throws DuplicateLeadException When the lead has already been submitted.
     * @throws LeadNotFoundException
     * @throws LeadValidationException
     */
    public function checkLeadStatus(string $checkKey): CheckLeadStatusResponse
    {
        $req = new CheckLeadStatus($checkKey);

        $resp = $this->connector->send($req);

        return $resp->dto();
    }

    /**
     * Poll a lead until its auction finishes, using Phonexa's recommended intervals.
     *
     * Only useful for leads posted with closeConnection=1; a synchronous post has already
     * settled by the time it returns. Blocks the caller, so in Laravel this belongs in a
     * queued job rather than a web request.
     *
     * @param  int  $timeout  Seconds to keep polling before giving up.
     *
     * @throws LeadStatusTimeoutException When the lead is still in progress at the deadline.
     * @throws DuplicateLeadException When the lead has already been submitted.
     * @throws LeadNotFoundException
     * @throws LeadValidationException
     */
    public function waitForLeadStatus(string $checkKey, int $timeout = 120): CheckLeadStatusResponse
    {
        $start = $this->now();

        while (true) {
            $response = $this->checkLeadStatus($checkKey);

            if (! $response->isInProgress()) {
                return $response;
            }

            $elapsed = $this->now() - $start;

            if ($elapsed >= $timeout) {
                throw new LeadStatusTimeoutException($checkKey, $timeout);
            }

            // Never sleep past the deadline; the next loop should be the one that gives up.
            $this->sleepFor((int) min(
                $this->intervalFor($elapsed),
                max(1, (int) ceil($timeout - $elapsed)),
            ));
        }
    }

    /**
     * Phonexa's documented polling cadence, in seconds between checks.
     */
    private function intervalFor(float $elapsed): int
    {
        return match (true) {
            $elapsed <= 10 => 1,
            $elapsed <= 30 => 2,
            $elapsed <= 60 => 3,
            default        => 5,
        };
    }

    private function now(): float
    {
        return $this->clock instanceof Closure
            ? (float) ($this->clock)()
            : microtime(true);
    }

    private function sleepFor(int $seconds): void
    {
        if ($this->sleeper instanceof Closure) {
            ($this->sleeper)($seconds);

            return;
        }

        sleep($seconds);
    }
}
