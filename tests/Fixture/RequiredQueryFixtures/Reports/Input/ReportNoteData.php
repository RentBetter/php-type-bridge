<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A body whose keys are typed with an alias that admits null: an update may clear each, and a list
 * of them may hold one.
 *
 * @phpstan-import-type MaybeProduct from AliasedProductFilterData
 * @phpstan-type _self = array{
 *     note?: MaybeProduct,
 *     tags?: list<MaybeProduct>,
 * }
 */
final class ReportNoteData
{
    public ?string $note = null;

    /** @var list<string|null> */
    public array $tags = [];
}
