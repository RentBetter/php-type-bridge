<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Enum\ContactStatus;

/**
 * Read whole by its form: what it takes is only what this says — an enum's values, a project-wide
 * alias, and one the builder can't state.
 *
 * @phpstan-type _self = array{
 *     status: value-of<ContactStatus>,
 *     id: UuidStr,
 * }
 */
final class RefData
{
    public ?string $status = null;

    public ?string $id = null;
}
