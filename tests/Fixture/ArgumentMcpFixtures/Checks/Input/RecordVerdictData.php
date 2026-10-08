<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Severity;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Spec\Param;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-import-type _self from ReadingData as Reading
 * @phpstan-import-type _self from LabelOperations as Labels
 *
 * @phpstan-type _self = array{
 *     status: string,
 *     floor?: string,
 *     retries?: int,
 *     ratio?: float,
 *     note?: string,
 *     readings?: list<Reading>,
 *     labels?: Labels,
 * }
 */
final class RecordVerdictData
{
    #[Param('The verdict.')]
    public ?Severity $status = null;

    // The second Choice runs only when its group is asked for, so it narrows nothing a request
    // can rely on.
    #[Param(['The lowest status that alerts.', 'Never SKIPPED.'])]
    #[Assert\Choice(choices: [Severity::SKIPPED], match: false)]
    #[Assert\Choice(choices: [Severity::OK], groups: ['strict'])]
    public ?Severity $floor = null;

    // With the form's PositiveOrZero, two lower bounds: the tighter is the one that holds.
    #[Assert\Range(min: 1, max: 5)]
    #[Assert\LessThan(4)]
    public ?int $retries = null;

    // An upper bound read off another property is not one a schema can state.
    #[Assert\Range(min: 0.5, maxPropertyPath: 'retries')]
    public ?float $ratio = null;

    // Bytes are not characters, so the second Length is not a maxLength.
    #[Assert\Length(min: 3)]
    #[Assert\Length(max: 10, countUnit: Assert\Length::COUNT_BYTES)]
    public ?string $note = null;

    /** @var list<ReadingData> */
    #[Param('The evidence, one entry per figure.')]
    #[Assert\Count(min: 1, max: 20)]
    public array $readings = [];

    public ?LabelOperations $labels = null;
}
