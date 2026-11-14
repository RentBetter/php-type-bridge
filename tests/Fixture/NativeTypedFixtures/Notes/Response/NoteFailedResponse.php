<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractEnvelopeResponse;

final class NoteFailedResponse extends AbstractEnvelopeResponse implements HttpOk {}
