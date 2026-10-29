<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Notes\Response\ShowNoteResponse;

/**
 * A collector given a reader of class files places types, and reads them, as that reader does: one
 * module guesser, one docblock reader, one class locator, whichever the reader was made with.
 */
final class SharedReaderTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/NativeTypedFixtures';

    public function testACollectorGivenOnlyAReaderUsesItsGuesser(): void
    {
        $guesser = new DomainGuesser(2);
        $response = (new ResponseClassCollector(files: new ClassFileTypes(domainGuesser: $guesser)))->collectIndex(self::SRC)[ShowNoteResponse::class];

        self::assertSame($guesser->guess(self::SRC, $response->sourceFile), $response->domain);
    }

    /**
     * @return iterable<string, array{callable(): object}>
     */
    public static function collectors(): iterable
    {
        yield 'responses' => [static fn(): object => new ResponseClassCollector(domainGuesser: new DomainGuesser(2), files: new ClassFileTypes())];
        yield 'types' => [static fn(): object => new PhpDocTypeCollector(domainGuesser: new DomainGuesser(2), files: new ClassFileTypes())];
        yield 'endpoints' => [static fn(): object => new EndpointContractCollector(domainGuesser: new DomainGuesser(2), files: new ClassFileTypes())];
    }

    /**
     * @param callable(): object $make
     */
    #[DataProvider('collectors')]
    public function testAGuesserBesideAReaderThatIsNotItsIsRefused(callable $make): void
    {
        // It would place a response in one module and its imports in another.
        $this->expectException(InvalidArgumentException::class);

        $make();
    }
}
