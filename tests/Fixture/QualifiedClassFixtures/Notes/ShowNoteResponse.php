<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QualifiedClassFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** A class by its native type, and by a docblock naming it in full. */
final class ShowNoteResponse implements ApiResponse, HttpOk
{
    public \PTGS\TypeBridge\Tests\Fixture\QualifiedClassFixtures\Common\Money $total;

    /** @var \PTGS\TypeBridge\Tests\Fixture\QualifiedClassFixtures\Common\Money */
    public object $price;
}
