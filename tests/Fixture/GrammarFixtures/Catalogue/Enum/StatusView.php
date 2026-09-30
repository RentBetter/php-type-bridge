<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GrammarFixtures\Catalogue\Enum;

/**
 * A status enum's serialised form, as a status enum's jsonSerialize() declares it: the shared
 * envelope plus its own keys, with more allowed.
 *
 * @phpstan-import-type ApiEnumData from ApiEnumView
 *
 * @phpstan-type StatusData = ApiEnumData&array{ok: bool, value: int, ...}
 */
final class StatusView {}
