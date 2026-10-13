<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportListFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Response\ReportResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One endpoint whose query a caller cannot leave out, and one whose every parameter is optional.
 */
final class ReportController
{
    #[Route('/api/report', methods: ['GET'])]
    #[ApiRequest(query: ReportFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports', methods: ['GET'])]
    #[ApiRequest(query: ReportListFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
