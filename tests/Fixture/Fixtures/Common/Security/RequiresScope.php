<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security;

use Attribute;

/**
 * Stand-in for a project's auth-scope attribute (e.g. a TokenAccess attribute): the scope
 * values live in a public property as strings and/or string-backed enums, which is the shape
 * the collector's scope extraction reads.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class RequiresScope
{
    /** @var list<FixtureScope|string> */
    public array $scopes;

    /**
     * @param FixtureScope|string|list<FixtureScope|string> $scope
     */
    public function __construct(FixtureScope|string|array $scope)
    {
        $this->scopes = \is_array($scope) ? array_values($scope) : [$scope];
    }
}
