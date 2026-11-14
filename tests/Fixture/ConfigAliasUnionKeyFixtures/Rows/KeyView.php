<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConfigAliasUnionKeyFixtures\Rows;

/**
 * Maps keyed by unions of project-wide aliases, by an alias naming another, and by one written in
 * brackets.
 *
 * @phpstan-type Mixed = array{
 *     byTextOrInt: array<Text|int, string>,
 *     byNested: array<AnyOf, string>,
 *     byGrouped: array<Grouped, string>,
 *     byTextOrCode: array<Text|Code, string>,
 *     byNumOrString: array<Num|string, string>,
 * }
 */
final class KeyView {}
