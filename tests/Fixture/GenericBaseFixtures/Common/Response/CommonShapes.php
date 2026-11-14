<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response;

/**
 * The base's module declares a `Note` of its own: a binding naming the Notes class Note is not it.
 *
 * @phpstan-type Note = array{common: bool}
 * @phpstan-type NoteView = array{common: string}
 * @phpstan-type Tag = array{label: string}
 */
final class CommonShapes
{
}
