<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Command\GenerateTypesCommand;
use PTGS\TypeBridge\Form\DefaultValidationErrorResponseFactory;
use PTGS\TypeBridge\Form\RequestFormProcessor;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;
use PTGS\TypeBridge\Http\Include\IncludeResolver;
use PTGS\TypeBridge\Http\Include\IncludeResponseSubscriber;
use PTGS\TypeBridge\Http\Include\RefRegistry;
use PTGS\TypeBridge\Http\TypeBridgeResponseSubscriber;
use PTGS\TypeBridge\Http\TypeBridgeThrowableListener;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Thing;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\ThingNormalizer;
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

    public function test_the_include_runtime_is_off_unless_the_app_enables_it(): void
    {
        $container = $this->compileWithBundle();

        self::assertFalse($container->has(IncludeResponseSubscriber::class));
        self::assertFalse($container->has(IncludeResolver::class));
        self::assertFalse($container->has(RefRegistry::class));
    }

    public function test_enabling_includes_registers_its_subscriber_ahead_of_the_response_subscriber(): void
    {
        $container = $this->compileWithBundle(bundleConfig: ['includes' => true]);

        self::assertInstanceOf(IncludeResponseSubscriber::class, $container->get(IncludeResponseSubscriber::class));

        $viewTags = $container->getDefinition(IncludeResponseSubscriber::class)->getTag('kernel.event_listener');
        self::assertCount(1, $viewTags);
        self::assertSame(KernelEvents::VIEW, $viewTags[0]['event']);
        self::assertSame(0, $viewTags[0]['priority'], 'Ahead of TypeBridgeResponseSubscriber, at -1');
    }

    public function test_the_enum_format_header_and_encoding_options_are_configurable(): void
    {
        $container = $this->compileWithBundle(bundleConfig: ['includes' => ['enum_format_header' => 'X-Enums', 'json_encoding_options' => \JSON_UNESCAPED_UNICODE]]);

        self::assertSame('X-Enums', $container->getParameter('type_bridge.includes.enum_format_header'));
        self::assertSame(\JSON_UNESCAPED_UNICODE, $container->getParameter('type_bridge.includes.json_encoding_options'));
        self::assertInstanceOf(IncludeResponseSubscriber::class, $container->get(IncludeResponseSubscriber::class), 'Setting an option enables the runtime');
    }

    public function test_an_autoconfigured_ref_normalizer_is_found_by_the_registry(): void
    {
        $container = $this->compileWithBundle(
            bundleConfig: ['includes' => true],
            before: static function (ContainerBuilder $container): void {
                $container->register(ThingNormalizer::class)->setAutoconfigured(true);
            },
        );

        $registry = $container->get(RefRegistry::class);
        self::assertInstanceOf(RefRegistry::class, $registry);
        self::assertInstanceOf(ThingNormalizer::class, $registry->for(Thing::class));
    }

    /**
     * @param (callable(ContainerBuilder): void)|null $configure
     * @param array<string, mixed> $bundleConfig the app's `type_bridge:` config
     * @param (callable(ContainerBuilder): void)|null $before app services, registered before compiling
     */
    private function compileWithBundle(?callable $configure = null, array $bundleConfig = [], ?callable $before = null): ContainerBuilder
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
        $container->loadFromExtension($extension->getAlias(), $bundleConfig);
        if (null !== $before) {
            $before($container);
        }

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
                    IncludeResponseSubscriber::class,
                    IncludeResolver::class,
                    RefRegistry::class,
                ],
                $configure,
            ),
        );

        $container->compile();

        return $container;
    }
}
