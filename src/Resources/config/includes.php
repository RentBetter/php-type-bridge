<?php

declare(strict_types=1);

use PTGS\TypeBridge\Http\Include\IncludeResolver;
use PTGS\TypeBridge\Http\Include\IncludeResponseSubscriber;
use PTGS\TypeBridge\Http\Include\RefRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * The include/expand runtime, imported by TypeBridgeBundle only when `type_bridge.includes` is
 * enabled. The subscriber's #[AsEventListener] (priority 0) puts it ahead of
 * TypeBridgeResponseSubscriber (priority -1); RefRegistry collects every RefNormalizer through
 * the tag the bundle autoconfigures.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(RefRegistry::class);
    $services->set(IncludeResolver::class);
    $services->set(IncludeResponseSubscriber::class)
        ->arg('$enumFormatHeader', param('type_bridge.includes.enum_format_header'))
        ->arg('$encodingOptions', param('type_bridge.includes.json_encoding_options'));
};
