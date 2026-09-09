<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Common\Spec;

use Attribute;

/**
 * Stand-in for a project's endpoint-documentation attribute (property-api's Spec\Api): the
 * text lives in a public `description` that is a string or a list of strings, which is the
 * shape the collector reads when a tool inherits its description.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Api
{
    /**
     * @param string|list<string> $description
     */
    public function __construct(
        public string|array $description,
    ) {}
}
