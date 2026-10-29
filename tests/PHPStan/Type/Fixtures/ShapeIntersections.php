<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type\Fixtures;

use function PHPStan\Testing\assertType;

/**
 * @phpstan-type BaseData = array{id: string, name?: string}
 * @phpstan-type AdminData = BaseData & array{'$admin': array{email: string}}
 * @phpstan-type OpenData = array{id: string, ...}
 * @phpstan-type OpenExtendedData = OpenData & array{flag: bool, ...}
 * @phpstan-type ClashData = BaseData & array{id: int}
 */
final class ShapeIntersections
{
    /**
     * @param AdminData $admin
     * @param OpenExtendedData $open
     * @param ClashData $clash
     */
    public function read(array $admin, array $open, array $clash): void
    {
        assertType("array{id: string, name?: string, '\$admin': array{email: string}}", $admin);
        // PHPStan reads an open shape's intersection itself, and a key two parts both declare is
        // left unresolved, as before
        assertType('array{id: string, flag: bool, ...}', $open);
        assertType('array', $clash);
    }

    /**
     * @return AdminData
     */
    public function extend(): array
    {
        return [...$this->base(), '$admin' => ['email' => 'a@example.com']];
    }

    /**
     * @return BaseData
     */
    private function base(): array
    {
        return ['id' => 'a'];
    }
}
