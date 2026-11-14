<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SelfSerialisingEnumFixtures\Notes;

/** Serialises itself, with no `_self` to say how: not its values. */
enum Badge: string implements \JsonSerializable
{
    case Gold = 'gold';

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return ['label' => $this->value];
    }
}
