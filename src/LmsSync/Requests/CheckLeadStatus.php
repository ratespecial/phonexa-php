<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Requests;

use Ratespecial\Phonexa\LmsSync\Exceptions\DuplicateLeadException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadNotFoundException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadValidationException;
use Ratespecial\Phonexa\LmsSync\Responses\AbstractLeadResponse;
use Ratespecial\Phonexa\LmsSync\Responses\CheckLeadStatusResponse;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Request\CreatesDtoFromResponse;

/**
 * Ask Phonexa what became of a lead posted with closeConnection=1.
 */
class CheckLeadStatus extends Request implements HasBody
{
    use CreatesDtoFromResponse;
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $checkKey  The `lead_id` returned by PostLead.
     */
    public function __construct(
        public string $checkKey,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/lead/check-lead-status';
    }

    protected function defaultBody(): array
    {
        return [
            'checkKey' => $this->checkKey,
        ];
    }

    /**
     * @throws DuplicateLeadException When the lead has already been submitted.
     * @throws LeadNotFoundException
     * @throws LeadValidationException
     */
    public function createDtoFromResponse(Response $response): CheckLeadStatusResponse
    {
        /** @var array<string, mixed> $body */
        $body = $response->json();

        $status = (int) ($body['status'] ?? 0);

        if ($status === AbstractLeadResponse::STATUS_NOT_FOUND) {
            throw new LeadNotFoundException($response, $this->checkKey);
        }

        if ($status === AbstractLeadResponse::STATUS_ERROR) {
            throw LeadValidationException::fromBody($response, $body);
        }

        return CheckLeadStatusResponse::fromResponse($body);
    }
}
