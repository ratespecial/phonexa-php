<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Contracts;

/**
 * Implemented by anything that can describe itself as a Phonexa lead.
 *
 * Phonexa only defines four universal system fields — apiId, apiPassword, productId and
 * tPar. Every other field is ad-hoc and differs per product, so this contract deliberately
 * says nothing about which fields exist. Implement it on your own Eloquent model, DTO or
 * form object and it becomes postable via LmsSyncService::postLead().
 */
interface ProvidesLeadData
{
    /**
     * Every field to send to Phonexa, keyed by the exact, case-sensitive Phonexa field name.
     *
     * System fields may be included. Credentials left out here are supplied by the
     * connector's authenticator, so most implementations should omit them.
     *
     * @return array<string, mixed>
     */
    public function getLeadData(): array;
}
