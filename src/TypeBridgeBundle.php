<?php

declare(strict_types=1);

namespace PTGS\TypeBridge;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Auto-registers the bundle's runtime services (HTTP listeners, form processor, status
 * resolver, generate-types command, validation-error factory) so a consuming app that
 * enables the bundle needs zero manual services.yaml.
 *
 * Apps override the ValidationErrorResponseFactory binding in their own service config
 * to render their app-specific 422 envelope.
 */
final class TypeBridgeBundle extends AbstractBundle
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');
    }
}
