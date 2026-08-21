<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Requests;

use Ratespecial\Phonexa\LmsSync\Contracts\ProvidesLeadData;
use Ratespecial\Phonexa\LmsSync\Exceptions\DuplicateLeadException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadValidationException;
use Ratespecial\Phonexa\LmsSync\Responses\AbstractLeadResponse;
use Ratespecial\Phonexa\LmsSync\Responses\PostLeadResponse;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Request\CreatesDtoFromResponse;

/**
 * Post a lead into Phonexa to be sold.
 *
 * The endpoint also accepts form encoding, but JSON is used here because it nests the tPar
 * pass-through map natively rather than needing tPar[key] bracket syntax.
 */
class PostLead extends Request implements HasBody
{
    use CreatesDtoFromResponse;
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        public ProvidesLeadData $lead,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/lead/';
    }

    protected function defaultBody(): array
    {
        return $this->lead->getLeadData();
    }

    /**
     * @throws DuplicateLeadException When the lead has already been submitted.
     * @throws LeadValidationException
     */
    public function createDtoFromResponse(Response $response): PostLeadResponse
    {
        /** @var array<string, mixed> $body */
        $body = $response->json();

        $leadStatus = (int) ($body['status'] ?? 0);

        if ($leadStatus === AbstractLeadResponse::STATUS_ERROR) {
            throw LeadValidationException::fromBody($response, $body);
        }

        return PostLeadResponse::fromResponse($body);
    }
}
