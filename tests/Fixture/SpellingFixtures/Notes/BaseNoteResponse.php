<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SpellingFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** A base documented with PHPStan's other spellings of its scalars. */
abstract class BaseNoteResponse implements ApiResponse, HttpOk
{
    /** @var integer */
    public $count = 0;

    /** @var boolean */
    public $open = true;

    /** @var double */
    public $ratio = 0.0;
}
