<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PartialInheritedQueryFixtures\Reports\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\PartialInheritedQueryFixtures\Reports\Form\ReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\PartialInheritedQueryFixtures\Reports\Response\ReportResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController
{
    #[Route('/api/reports', methods: ['GET'])]
    #[ApiRequest(query: ReportFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
