<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Auth;

use Saloon\Contracts\Authenticator;
use Saloon\Http\PendingRequest;
use Saloon\Repositories\Body\ArrayBodyRepository;

/**
 * Sends apiId and apiPassword in the request body, as LMS Sync expects.
 *
 * Values already present in the body win, which is what lets an individual lead target a
 * different Phonexa account than the connector was configured with. Saloon merges the body
 * before authenticating, so anything the lead supplied is visible here.
 */
class CredentialsAuthenticator implements Authenticator
{
    public function __construct(
        private readonly string $apiId,
        private readonly string $apiPassword,
    ) {}

    public function set(PendingRequest $pendingRequest): void
    {
        $body = $pendingRequest->body();

        if (! $body instanceof ArrayBodyRepository) {
            return;
        }

        $data = $body->all();

        if (empty($data['apiId'])) {
            $body->add('apiId', $this->apiId);
        }

        if (empty($data['apiPassword'])) {
            $body->add('apiPassword', $this->apiPassword);
        }
    }
}
