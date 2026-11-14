<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TraitSelfBindingFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

final class ShowKindResponse implements ApiResponse, HttpOk
{
    use Outer;

    public const string KIND_A = 'a';
    public const string KIND_B = 'b';
}
