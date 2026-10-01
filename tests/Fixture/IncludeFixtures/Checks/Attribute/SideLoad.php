<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludeFixtures\Checks\Attribute;

use Attribute;

/** Marks a response property that is a side-loaded collection, as an application would. */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class SideLoad {}
