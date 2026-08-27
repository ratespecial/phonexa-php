<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync;

use Ratespecial\Phonexa\Exceptions\PhonexaException;
use Ratespecial\Phonexa\LmsSync\Auth\CredentialsAuthenticator;
use Ratespecial\Phonexa\LmsSync\Requests\PostLead;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Repositories\Body\ArrayBodyRepository;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Throwable;

/**
 * Connector for the Phonexa LMS Sync API — posting leads to be sold.
 *
 * The base URL is instance-specific (leads-inst<instance>-client.phonexa.com), so it is
 * always injected rather than hard-coded.
 */
class LmsSyncConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public ?int $tries = 3;

    public ?int $retryInterval = 1000;

    public ?bool $useExponentialBackoff = true;

    /**
     * @var string Application name included in the User-Agent header. Set by the service provider.
     */
    public string $applicationName = '';

    /**
     * @var bool Adds testMode=1 to every posted lead, so nothing is sold for real.
     */
    public bool $testMode = false;

    /**
     * @var int|null Applied to posted leads that do not name a product of their own.
     */
    public ?int $defaultProductId = null;

    public function __construct(
        public string $baseUrl = '',
        private readonly string $apiId = '',
        private readonly string $apiPassword = '',
    ) {}

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $userAgent = 'lib.phonexa';

        if ($this->applicationName !== '') {
            $userAgent .= " ($this->applicationName)";
        }

        return [
            'User-Agent' => $userAgent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            // Phonexa's own integration examples allow 120s; lead auctions really can run that long.
            'timeout'         => 120,
            'connect_timeout' => 10,
        ];
    }

    /**
     * Credentials are optional here: a lead may carry its own pair, and the authenticator only
     * rejects a request that ends up with neither the connector's nor its own.
     */
    protected function defaultAuth(): CredentialsAuthenticator
    {
        return new CredentialsAuthenticator($this->apiId, $this->apiPassword);
    }

    /**
     * Apply connector-wide lead defaults.
     *
     * Runs after the body is merged and after authentication, so anything the lead itself
     * set is already present and is left alone.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        if (! $pendingRequest->getRequest() instanceof PostLead) {
            return;
        }

        $body = $pendingRequest->body();

        if (! $body instanceof ArrayBodyRepository) {
            return;
        }

        $data = $body->all();

        if ($this->testMode && ! isset($data['testMode'])) {
            $body->add('testMode', 1);
        }

        if ($this->defaultProductId !== null && empty($data['productId'])) {
            $body->add('productId', $this->defaultProductId);
        }
    }

    /**
     * Posting a lead is never retried.
     *
     * Phonexa offers no idempotency key, so a replayed POST /lead/ can be auctioned and sold
     * a second time. A duplicate sale is far worse than a failed post, so only the read-only
     * status check is allowed to retry.
     */
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($request instanceof PostLead) {
            return false;
        }

        if ($exception instanceof FatalRequestException) {
            return true;
        }

        return $exception->getResponse()->status() >= 500;
    }

    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return new PhonexaException($response, previous: $senderException);
    }
}
