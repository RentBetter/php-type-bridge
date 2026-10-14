<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Form\ScopedReportFilterType;
use PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Response\ReportResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController
{
    #[Route('/api/reports/scoped', methods: ['GET'])]
    #[ApiRequest(query: ScopedReportFilterType::class)]
    #[ApiResponses([ReportResponse::class])]
    public function showScopedReport(): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{code}', methods: ['GET'], requirements: ['code' => '\d+'], utf8: true)]
    #[ApiResponses([ReportResponse::class])]
    public function showReport(string $code): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{code}/raw', methods: ['GET'], requirements: ['code' => '\d+'], options: ['utf8' => false])]
    #[ApiResponses([ReportResponse::class])]
    public function showRawReport(string $code): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/reports/{code}/plain', methods: ['GET'], requirements: ['code' => '\d+'])]
    #[ApiResponses([ReportResponse::class])]
    public function showPlainReport(string $code): ReportResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
