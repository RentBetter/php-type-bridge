<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QualifiedEnumFixtures\Notes;

/**
 * The same enum, written whole and by its short name.
 *
 * @phpstan-type _self array{status: value-of<\PTGS\TypeBridge\Tests\Fixture\QualifiedEnumFixtures\Notes\Status>, other: value-of<Status>}
 */
final class NoteView {}
