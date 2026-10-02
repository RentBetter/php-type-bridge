<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ExpandFixtures\Checks\View;

/**
 * A check references its definition and owner by id, and a definition its latest result: each a
 * record a request can expand in place of the id.
 *
 * @phpstan-type ResultData = array{id: string, info: string}
 * @phpstan-type DefinitionData = array{id: string, name: string, latestResult?: ref<ResultData>}
 * @phpstan-type CheckData = array{
 *     name: string,
 *     definition?: ref<DefinitionData>,
 *     owner: ref<DefinitionData>,
 *     related: list<ref<ResultData>>,
 * }
 */
final class CheckView {}
