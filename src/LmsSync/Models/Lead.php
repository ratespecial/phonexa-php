<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Models;

use Ratespecial\Phonexa\LmsSync\Contracts\ProvidesLeadData;

/**
 * A generic Phonexa lead.
 *
 * Carries the four system fields as typed properties and accepts any number of ad-hoc,
 * product-specific fields alongside them. Because the ad-hoc field set changes per product,
 * nothing here whitelists field names — but Phonexa field names ARE case sensitive, so keys
 * must be written exactly as the product's API doc spells them.
 *
 * Declared properties are never routed through __get()/__set(), so `$lead->apiId` reaches the
 * typed property while `$lead->firstName` lands in the ad-hoc bag.
 */
class Lead implements ProvidesLeadData
{
    /**
     * @var string|null Overrides the connector's configured API ID when set.
     */
    public ?string $apiId = null;

    /**
     * @var string|null Overrides the connector's configured API password when set.
     */
    public ?string $apiPassword = null;

    /**
     * @var int|null The Phonexa product this lead is being posted against.
     */
    public ?int $productId = null;

    /**
     * @var array<string, scalar> Pass-through params Phonexa forwards to buyers but does not store.
     */
    public array $tPar = [];

    /**
     * @var array<string, mixed> Ad-hoc, product-specific fields keyed by exact Phonexa field name.
     */
    protected array $fields = [];

    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fill($fields);
    }

    /**
     * Mass-assign fields, routing system field names to their typed properties.
     *
     * @param  array<string, mixed>  $fields
     */
    public function fill(array $fields): static
    {
        foreach ($fields as $field => $value) {
            match ($field) {
                'apiId'       => $this->apiId          = $value === null ? null : (string) $value,
                'apiPassword' => $this->apiPassword    = $value === null ? null : (string) $value,
                'productId'   => $this->productId      = $value === null ? null : (int) $value,
                'tPar'        => $this->tPar           = is_array($value) ? $value : [],
                default       => $this->fields[$field] = $value,
            };
        }

        return $this;
    }

    /**
     * Set a single ad-hoc field.
     */
    public function set(string $field, mixed $value): static
    {
        return $this->fill([$field => $value]);
    }

    /**
     * Read a single ad-hoc field.
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return $this->fields[$field] ?? $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    public function forget(string $field): static
    {
        unset($this->fields[$field]);

        return $this;
    }

    /**
     * Add a single pass-through parameter.
     */
    public function setTPar(string $key, string|int|float $value): static
    {
        $this->tPar[$key] = $value;

        return $this;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function getLeadData(): array
    {
        $data = $this->fields;

        if ($this->apiId !== null) {
            $data['apiId'] = $this->apiId;
        }

        if ($this->apiPassword !== null) {
            $data['apiPassword'] = $this->apiPassword;
        }

        if ($this->productId !== null) {
            $data['productId'] = $this->productId;
        }

        if ($this->tPar !== []) {
            $data['tPar'] = $this->tPar;
        }

        return $data;
    }
}
