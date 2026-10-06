<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Spec;

use Attribute;

/**
 * Stand-in for a project's parameter-documentation attribute (property-api's Spec\Param): the
 * text lives in a public `description` that is a string or a list of strings.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Param
{
    /**
     * @param string|list<string> $description
     */
    public function __construct(
        public string|array $description,
    ) {}
}
