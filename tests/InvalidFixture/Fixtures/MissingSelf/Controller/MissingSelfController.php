<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingSelf\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingSelf\Form\MissingSelfRequestType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingSelf\Response\MissingSelfResponse;

final class MissingSelfController
{
    #[ApiRequest(body: MissingSelfRequestType::class)]
    #[ApiResponses([MissingSelfResponse::class])]
    public function create(): MissingSelfResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
