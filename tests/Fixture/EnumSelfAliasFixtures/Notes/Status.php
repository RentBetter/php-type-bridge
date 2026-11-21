<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumSelfAliasFixtures\Notes;

/**
 * Sent as its shape, StatusData: its values are no type of its module's, whose `Status` is an alias.
 *
 * @phpstan-type _self = array{
 *     code: string,
 * }
 */
enum Status: string
{
    case Draft = 'draft';
}
