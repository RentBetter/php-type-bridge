<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AliasedFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AliasedNoteType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AliasedProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AliasedRegionFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AliasedScopeFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AnyProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\AnyRegionFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ConfigProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ImportedProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\LayeredProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\LayeredRegionFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\LeftOutProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\MonthlyReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\NorthRegionFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\NullableProductFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\OpenRegionFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\QuarterlyReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportFieldsType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportListFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportNoteType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportPlaceType;
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

    #[Route('/api/reports/north', methods: ['GET'])]
    #[ApiRequest(query: NorthRegionFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listNorthReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/open', methods: ['GET'])]
    #[ApiRequest(query: OpenRegionFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showOpenRegionReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/layered', methods: ['GET'])]
    #[ApiRequest(query: LayeredRegionFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listLayeredReports(): ReportResponse
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

    #[Route('/api/reports/aliasedProduct', methods: ['GET'])]
    #[ApiRequest(query: AliasedProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listAliasedProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/importedProduct', methods: ['GET'])]
    #[ApiRequest(query: ImportedProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listImportedProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/configProduct', methods: ['GET'])]
    #[ApiRequest(query: ConfigProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listConfigProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/aliasedScope', methods: ['GET'])]
    #[ApiRequest(query: AliasedScopeFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listAliasedScopeReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/aliasedRegion', methods: ['GET'])]
    #[ApiRequest(query: AliasedRegionFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showAliasedRegionReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/layeredProduct', methods: ['GET'])]
    #[ApiRequest(query: LayeredProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listLayeredProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/leftOutProduct', methods: ['GET'])]
    #[ApiRequest(query: LeftOutProductFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function listLeftOutProductReports(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{id}/place', methods: ['PATCH'])]
    #[ApiRequest(body: ReportPlaceType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function updateReportPlace(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{id}/fields', methods: ['PUT'])]
    #[ApiRequest(body: ReportFieldsType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function updateReportFields(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{id}/note', methods: ['PUT'])]
    #[ApiRequest(body: ReportNoteType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function updateReportNote(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{id}/aliased-note', methods: ['PUT'])]
    #[ApiRequest(body: AliasedNoteType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function updateAliasedNote(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/aliased-filter', methods: ['GET'])]
    #[ApiRequest(query: AliasedFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showAliasedFilterReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
