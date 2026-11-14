<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

/**
 * @phpstan-type _self = array{
 *     text?: string,
 * }
 */
final class MemoData
{
    public ?string $text = null;
}
