<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\NullableProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportListFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\WeeklyReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Response\ReportResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Queries a caller can and cannot leave out: required keys of its own, required keys it inherits
 * through an intersection, and keys that are all optional — `?`-keyed, or valued `?T`.
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

    #[Route('/api/reports/weekly', methods: ['GET'])]
    #[ApiRequest(query: WeeklyReportFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showWeeklyReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/byProduct', methods: ['GET'])]
    #[ApiRequest(query: NullableProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
