<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\DependencyInjection\Fixtures;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs after the bundle extension has registered its services (which only happens during
 * compilation) and marks the given ids public, plus runs any extra app-config callback,
 * so a test can fetch the services after compile.
 */
final class PublicizePass implements CompilerPassInterface
{
    /**
     * @param list<class-string> $ids
     * @param (callable(ContainerBuilder): void)|null $configure
     */
    public function __construct(
        private readonly array $ids,
        private readonly mixed $configure = null,
    ) {}

    public function process(ContainerBuilder $container): void
    {
        if (null !== $this->configure) {
            ($this->configure)($container);
        }

        foreach ($this->ids as $id) {
            if ($container->hasAlias($id)) {
                $container->getAlias($id)->setPublic(true);

                continue;
            }
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }
}
