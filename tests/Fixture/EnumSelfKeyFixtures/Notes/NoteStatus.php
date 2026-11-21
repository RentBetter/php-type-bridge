<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumSelfKeyFixtures\Notes;

/**
 * Sent as the shape its `_self` declares, NoteStatusData, as its TypeScript names it.
 *
 * @phpstan-type _self = array{
 *     code: string,
 *     label: string,
 * }
 */
enum NoteStatus: string implements \JsonSerializable
{
    case Open = 'open';

    /**
     * @return _self
     */
    public function jsonSerialize(): array
    {
        return ['code' => $this->value, 'label' => ucfirst($this->value)];
    }
}
