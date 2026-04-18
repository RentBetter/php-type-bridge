<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

/**
 * @phpstan-import-type _self from AdvancedOwnerData as AdvancedOwner
 *
 * @phpstan-type _self = array{
 *     title: string,
 *     enabled?: bool,
 *     settings?: array<string, mixed>,
 *     startsAt: string,
 *     tags?: list<string>,
 *     state: value-of<AdvancedState>,
 *     ?assignee: string,
 *     ?owner: AdvancedOwner,
 * }
 */
final class AdvancedRequestData
{
    public ?string $title = null;
    public ?bool $enabled = null;

    /** @var array<string, mixed>|null */
    public ?array $settings = null;

    public ?\DateTimeImmutable $startsAt = null;

    /** @var list<string>|null */
    public ?array $tags = null;

    public ?AdvancedState $state = null;
    public ?string $ownerId = null;
    public ?AdvancedOwnerData $owner = null;
}
