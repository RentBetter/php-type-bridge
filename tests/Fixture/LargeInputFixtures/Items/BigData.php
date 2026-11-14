<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LargeInputFixtures\Items;

/**
 * Sixty keys of one class with twenty alias-typed keys, and one more: no cycle anywhere, read
 * through in full.
 *
 * @phpstan-type MaybeText = string|null
 * @phpstan-type _self = array{
 *     item1?: ItemData,
 *     item2?: ItemData,
 *     item3?: ItemData,
 *     item4?: ItemData,
 *     item5?: ItemData,
 *     item6?: ItemData,
 *     item7?: ItemData,
 *     item8?: ItemData,
 *     item9?: ItemData,
 *     item10?: ItemData,
 *     item11?: ItemData,
 *     item12?: ItemData,
 *     item13?: ItemData,
 *     item14?: ItemData,
 *     item15?: ItemData,
 *     item16?: ItemData,
 *     item17?: ItemData,
 *     item18?: ItemData,
 *     item19?: ItemData,
 *     item20?: ItemData,
 *     item21?: ItemData,
 *     item22?: ItemData,
 *     item23?: ItemData,
 *     item24?: ItemData,
 *     item25?: ItemData,
 *     item26?: ItemData,
 *     item27?: ItemData,
 *     item28?: ItemData,
 *     item29?: ItemData,
 *     item30?: ItemData,
 *     item31?: ItemData,
 *     item32?: ItemData,
 *     item33?: ItemData,
 *     item34?: ItemData,
 *     item35?: ItemData,
 *     item36?: ItemData,
 *     item37?: ItemData,
 *     item38?: ItemData,
 *     item39?: ItemData,
 *     item40?: ItemData,
 *     item41?: ItemData,
 *     item42?: ItemData,
 *     item43?: ItemData,
 *     item44?: ItemData,
 *     item45?: ItemData,
 *     item46?: ItemData,
 *     item47?: ItemData,
 *     item48?: ItemData,
 *     item49?: ItemData,
 *     item50?: ItemData,
 *     item51?: ItemData,
 *     item52?: ItemData,
 *     item53?: ItemData,
 *     item54?: ItemData,
 *     item55?: ItemData,
 *     item56?: ItemData,
 *     item57?: ItemData,
 *     item58?: ItemData,
 *     item59?: ItemData,
 *     item60?: ItemData,
 *     memo?: MaybeText,
 * }
 */
final class BigData
{
}
