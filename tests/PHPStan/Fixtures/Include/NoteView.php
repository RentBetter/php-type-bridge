<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include;

use DateTimeImmutable;

/**
 * @phpstan-type _self = array{id: string, author?: ref<array{id: string}>, at: DateTimeImmutable}
 */
final class NoteView {}
