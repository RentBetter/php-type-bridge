<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Tests\InvalidFixture\InvalidFixtureProject;
use RuntimeException;

final class RequestContractCollectorTest extends TestCase
{
    public function test_it_requires_api_request_forms_to_configure_a_data_class(): void
    {
        $srcDir = InvalidFixtureProject::srcDir('MissingDataClass');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must configure a non-null data_class');

        (new EndpointContractCollector())->collect($srcDir, (new ResponseClassCollector())->collectIndex($srcDir));
    }

    public function test_it_requires_request_data_classes_to_declare_self(): void
    {
        $srcDir = InvalidFixtureProject::srcDir('MissingSelf');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must declare @phpstan-type _self');

        (new EndpointContractCollector())->collect($srcDir, (new ResponseClassCollector())->collectIndex($srcDir));
    }
}
