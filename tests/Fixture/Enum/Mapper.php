<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Enum;

final class Mapper
{
    public static function idFor(Colour $colour): string
    {
        return 'M_' . $colour->name;
    }
}
