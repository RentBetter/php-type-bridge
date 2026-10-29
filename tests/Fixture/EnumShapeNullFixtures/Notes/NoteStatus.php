<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumShapeNullFixtures\Notes;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     hint: string|null,
 * }
 */
enum NoteStatus: string
{
    case Open = 'open';
}
