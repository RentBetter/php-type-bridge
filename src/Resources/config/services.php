<?php

declare(strict_types=1);

use PTGS\TypeBridge\Command\GenerateTypesCommand;
use PTGS\TypeBridge\Form\DefaultValidationErrorResponseFactory;
use PTGS\TypeBridge\Form\RequestFormProcessor;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;
use PTGS\TypeBridge\Http\TypeBridgeResponseSubscriber;
use PTGS\TypeBridge\Http\TypeBridgeThrowableListener;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Auto-registers every runtime service the bundle ships so a consuming app needs zero
 * manual services.yaml. autowire + autoconfigure picks up the #[AsEventListener] and
 * #[AsCommand] attributes.
 *
 * The ValidationErrorResponseFactory binding defaults to the bundle's lean response.
 * Apps override it by aliasing ValidationErrorResponseFactory to their own factory in
 * their service config (later definitions win).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(StatusCodeResolver::class);
    $services->set(EnumResolver::class);

    $services->set(GenerateTypesCommand::class);

    $services->set(TypeBridgeResponseSubscriber::class);
    $services->set(TypeBridgeThrowableListener::class);

    $services->set(RequestFormProcessor::class);
    $services->set(DefaultValidationErrorResponseFactory::class);
    $services->alias(ValidationErrorResponseFactory::class, DefaultValidationErrorResponseFactory::class);
};
