<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LeftOutFixtures\Orders;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every key may be left out as the contract reads it; what the server makes of one left out is what
 * a new OrderData holds there, against its property's constraints, or its form's blank.
 *
 * @phpstan-type _self = array{
 *     enabled?: bool,
 *     qty?: int,
 *     note?: string,
 *     label?: string,
 *     memo?: string,
 *     channel?: string,
 *     shipping?: ShippingData,
 * }
 */
final class OrderData
{
    #[Assert\NotNull]
    public ?bool $enabled = null;

    #[Assert\Positive]
    public ?int $qty = null;

    #[Assert\NotNull]
    public ?string $note = null;

    #[Assert\NotNull]
    public ?string $label = 'standard';

    /** Not cascaded into: its own constraints are never validated. */
    public ?ShippingData $shipping = null;

    public function __construct(
        #[Assert\NotNull]
        public ?string $channel = 'web',
    ) {}
}
