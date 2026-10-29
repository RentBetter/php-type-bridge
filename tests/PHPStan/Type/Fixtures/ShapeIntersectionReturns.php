<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type\Fixtures;

/**
 * @phpstan-type BaseData = array{id: string, name?: string}
 * @phpstan-type AdminData = BaseData & array{'$admin': array{email: string}}
 * @phpstan-type OptionalAdminData = BaseData & array{'$admin'?: array{email: string}}
 */
final class ShapeIntersectionReturns
{
    /**
     * @return AdminData
     */
    public function spreadsTheBase(): array
    {
        return [...$this->base(), '$admin' => ['email' => 'a@example.com']];
    }

    /**
     * @return AdminData
     */
    public function leavesOutTheExtension(): array
    {
        return $this->base();
    }

    /**
     * @return AdminData
     */
    public function mistypesTheExtension(): array
    {
        return [...$this->base(), '$admin' => ['email' => 1]];
    }

    /**
     * @return AdminData
     */
    public function mistypesTheBase(): array
    {
        return ['id' => 'a', 'name' => 1, '$admin' => ['email' => 'a@example.com']];
    }

    /**
     * @return OptionalAdminData
     */
    public function mistypesAnOptionalExtension(): array
    {
        return [...$this->base(), '$admin' => ['email' => 1]];
    }

    /**
     * @return BaseData
     */
    private function base(): array
    {
        return ['id' => 'a'];
    }
}
