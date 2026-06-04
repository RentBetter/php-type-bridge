<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Command\GenerateTypesCommand;
use PTGS\TypeBridge\Form\DefaultValidationErrorResponseFactory;
use PTGS\TypeBridge\Form\RequestFormProcessor;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;
use PTGS\TypeBridge\Http\TypeBridgeResponseSubscriber;
use PTGS\TypeBridge\Http\TypeBridgeThrowableListener;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\TypeBridgeBundle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Proves the bundle's extension registers every runtime service with autowiring and
 * autoconfiguration, so a consuming app needs zero manual services.yaml. The host's
 * FormFactoryInterface (normally provided by FrameworkBundle) and the #[AsEventListener]
 * autoconfigurator are registered here to mirror a real kernel.
 */
final class TypeBridgeBundleExtensionTest extends TestCase
{
    public function test_it_registers_runtime_services_with_zero_manual_config(): void
    {
        $container = $this->compileWithBundle();

        self::assertInstanceOf(StatusCodeResolver::class, $container->get(StatusCodeResolver::class));
        self::assertInstanceOf(EnumResolver::class, $container->get(EnumResolver::class));
        self::assertInstanceOf(GenerateTypesCommand::class, $container->get(GenerateTypesCommand::class));
        self::assertInstanceOf(TypeBridgeResponseSubscriber::class, $container->get(TypeBridgeResponseSubscriber::class));
        self::assertInstanceOf(TypeBridgeThrowableListener::class, $container->get(TypeBridgeThrowableListener::class));
        self::assertInstanceOf(RequestFormProcessor::class, $container->get(RequestFormProcessor::class));
    }

    public function test_it_binds_the_default_validation_error_response_factory(): void
    {
        $container = $this->compileWithBundle();

        $factory = $container->get(ValidationErrorResponseFactory::class);

        self::assertInstanceOf(DefaultValidationErrorResponseFactory::class, $factory);
    }

    public function test_an_app_can_override_the_validation_error_response_factory_binding(): void
    {
        $container = $this->compileWithBundle(static function (ContainerBuilder $container): void {
            $container->register(
                Fixtures\AppValidationErrorResponseFactory::class,
                Fixtures\AppValidationErrorResponseFactory::class,
            )
                ->setAutowired(true)
                ->setPublic(true);
            $container->setAlias(ValidationErrorResponseFactory::class, Fixtures\AppValidationErrorResponseFactory::class)
                ->setPublic(true);
        });

        self::assertInstanceOf(
            Fixtures\AppValidationErrorResponseFactory::class,
            $container->get(ValidationErrorResponseFactory::class),
        );
    }

    public function test_it_autoconfigures_the_view_and_exception_listeners(): void
    {
        $container = $this->compileWithBundle();

        $viewTags = $container->getDefinition(TypeBridgeResponseSubscriber::class)->getTag('kernel.event_listener');
        self::assertCount(1, $viewTags);
        self::assertSame(KernelEvents::VIEW, $viewTags[0]['event']);
        self::assertSame(-1, $viewTags[0]['priority']);

        $exceptionTags = $container->getDefinition(TypeBridgeThrowableListener::class)->getTag('kernel.event_listener');
        self::assertCount(1, $exceptionTags);
        self::assertSame(KernelEvents::EXCEPTION, $exceptionTags[0]['event']);
        self::assertSame(-1, $exceptionTags[0]['priority']);
    }

    /**
     * @param (callable(ContainerBuilder): void)|null $configure
     */
    private function compileWithBundle(?callable $configure = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        // Mirror what FrameworkBundle does for #[AsEventListener].
        $container->registerAttributeForAutoconfiguration(
            AsEventListener::class,
            static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
                $tagAttributes = get_object_vars($attribute);
                if ($reflector instanceof \ReflectionMethod) {
                    $tagAttributes['method'] = $reflector->getName();
                }
                $definition->addTag('kernel.event_listener', $tagAttributes);
            },
        );

        // The host application (FrameworkBundle + Form) provides the form factory.
        $container->setDefinition(
            FormFactoryInterface::class,
            (new Definition(FormFactoryInterface::class))
                ->setFactory([Forms::class, 'createFormFactory'])
                ->setPublic(true),
        );

        $bundle = new TypeBridgeBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias());

        // The extension only registers its services during compilation, so mark the
        // asserted ids public (and apply any app override) from a pass that runs after.
        $container->addCompilerPass(
            new Fixtures\PublicizePass(
                [
                    StatusCodeResolver::class,
                    EnumResolver::class,
                    GenerateTypesCommand::class,
                    TypeBridgeResponseSubscriber::class,
                    TypeBridgeThrowableListener::class,
                    RequestFormProcessor::class,
                    DefaultValidationErrorResponseFactory::class,
                    ValidationErrorResponseFactory::class,
                ],
                $configure,
            ),
        );

        $container->compile();

        return $container;
    }
}
