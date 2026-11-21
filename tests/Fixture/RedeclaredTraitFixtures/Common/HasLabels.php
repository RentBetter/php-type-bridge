<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Common;

/**
 * @phpstan-import-type Tag from CommonTagView
 */
trait HasLabels
{
    /** @var list<Tag> */
    public array $labels;
}
