<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A body whose keys are typed with an alias that admits null: an update may clear each, a list of
 * them may hold one, and so may a union with one among its members — and a key valued `?` of it,
 * left out when null, which its TypeScript (`label?: string | null`) also lets be sent as null.
 *
 * @phpstan-import-type MaybeProduct from AliasedProductFilterData
 * @phpstan-type _self = array{
 *     note?: MaybeProduct,
 *     tags?: list<MaybeProduct>,
 *     code?: MaybeProduct|int,
 *     label: ?MaybeProduct,
 * }
 */
final class ReportNoteData
{
    public ?string $note = null;

    /** @var list<string|null> */
    public array $tags = [];

    public string|int|null $code = null;

    public ?string $label = null;
}
