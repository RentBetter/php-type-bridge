<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CrossModuleEnumFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\CrossModuleEnumFixtures\Common\Response\AbstractStateResponse;

final class ShowNoteResponse extends AbstractStateResponse implements HttpOk {}
