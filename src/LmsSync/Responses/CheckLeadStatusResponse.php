<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Responses;

/**
 * Result of polling a previously posted lead.
 */
class CheckLeadStatusResponse extends AbstractLeadResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            status: (int) ($data['status'] ?? 0),
            statusText: (string) ($data['status_text'] ?? ''),
            redirectUrl: isset($data['redirect_url']) ? (string) $data['redirect_url'] : null,
        );
    }
}
