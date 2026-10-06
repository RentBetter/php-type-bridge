<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Severity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     status: string,
 *     floor?: string,
 * }
 */
final class RecordVerdictData
{
    public ?Severity $status = null;

    // The second Choice runs only when its group is asked for, so it narrows nothing a request
    // can rely on.
    #[Assert\Choice(choices: [Severity::SKIPPED], match: false)]
    #[Assert\Choice(choices: [Severity::OK], groups: ['strict'])]
    public ?Severity $floor = null;
}
