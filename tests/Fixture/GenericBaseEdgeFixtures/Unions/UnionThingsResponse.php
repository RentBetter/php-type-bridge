<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Unions;

/**
 * @phpstan-type Meta = array{page: int}
 * @phpstan-type Other = array{cursor: string}
 *
 * @extends PagedUnionResponse<Meta|Other>
 */
final class UnionThingsResponse extends PagedUnionResponse
{
}
