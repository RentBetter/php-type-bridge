<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Enum;

use PTGS\TypeBridge\Attribute\ValueOfName;

/**
 * Its value union is named otherwise: its own name is its `_self`.
 *
 * @phpstan-type _self = array{
 *     code: string,
 * }
 */
#[ValueOfName('ContactKindId')]
enum ContactKind: string
{
    case Person = 'person';
}
