<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Responses;

/**
 * Result of posting a lead to Phonexa.
 */
class PostLeadResponse extends AbstractLeadResponse
{
    public function __construct(
        int $status,
        string $statusText,
        ?string $redirectUrl = null,
        /**
         * @var string|null Phonexa's lead identifier. Pass this to checkLeadStatus() as the checkKey.
         */
        public readonly ?string $leadId = null,
        /**
         * @var float|null Sale price. Only present on a sold response.
         */
        public readonly ?float $price = null,
        public readonly ?float $timestamp = null,
        public readonly ?float $processingTime = null,
    ) {
        parent::__construct($status, $statusText, $redirectUrl);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            status: (int) ($data['status'] ?? 0),
            statusText: (string) ($data['status_text'] ?? ''),
            redirectUrl: isset($data['redirect_url']) ? (string) $data['redirect_url'] : null,
            leadId: isset($data['lead_id']) ? (string) $data['lead_id'] : null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            timestamp: isset($data['timestamp']) ? (float) $data['timestamp'] : null,
            processingTime: isset($data['processing_time']) ? (float) $data['processing_time'] : null,
        );
    }
}
