<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Form\MissingDataClassRequestType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Response\MissingDataClassResponse;

final class MissingDataClassController
{
    #[ApiRequest(body: MissingDataClassRequestType::class)]
    #[ApiResponses([MissingDataClassResponse::class])]
    public function create(): MissingDataClassResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
