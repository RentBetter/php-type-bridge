<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AnyProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AnyRegionFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\MonthlyReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\NullableProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\QuarterlyReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportFilterType;
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

    #[Route('/api/reports/quarterly', methods: ['GET'])]
    #[ApiRequest(query: QuarterlyReportFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showQuarterlyReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/monthly', methods: ['GET'])]
    #[ApiRequest(query: MonthlyReportFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showMonthlyReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/anyProduct', methods: ['GET'])]
    #[ApiRequest(query: AnyProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listAnyProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/anyRegion', methods: ['GET'])]
    #[ApiRequest(query: AnyRegionFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listAnyRegionReports(): ReportResponse
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
