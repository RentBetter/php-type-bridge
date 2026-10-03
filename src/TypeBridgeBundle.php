<?php

declare(strict_types=1);

namespace PTGS\TypeBridge;

use PTGS\TypeBridge\Http\Include\RefNormalizer;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Auto-registers the bundle's runtime services (HTTP listeners, form processor, status
 * resolver, generate-types command, validation-error factory) so a consuming app that
 * enables the bundle needs zero manual services.yaml.
 *
 * Apps override the ValidationErrorResponseFactory binding in their own service config
 * to render their app-specific 422 envelope.
 *
 * The include/expand runtime (src/Http/Include) is off unless the app turns it on:
 *
 *   type_bridge:
 *       includes: true          # or { enabled: true, enum_format_header: X-Enum-Format, … }
 *
 * Every autoconfigured RefNormalizer is tagged for it either way, which is inert while it is off.
 */
final class TypeBridgeBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('includes')
                    ->info('Write every TypeBridge response DTO through IncludeResolver, answering ?include=, ?expand= and the enum-format header.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('enum_format_header')
                            ->info('The request header whose value `id` asks for enums as their bare ids.')
                            ->defaultValue('X-Enum-Format')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('json_encoding_options')
                            ->info('json_encode() flags for the bodies it writes, as JsonResponse::setEncodingOptions() takes them.')
                            ->defaultValue(JsonResponse::DEFAULT_ENCODING_OPTIONS)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');

        $builder->registerForAutoconfiguration(RefNormalizer::class)->addTag(RefNormalizer::TAG);

        $includes = $config['includes'] ?? null;
        if (!\is_array($includes) || true !== ($includes['enabled'] ?? false)) {
            return;
        }

        $container->parameters()
            ->set('type_bridge.includes.enum_format_header', $includes['enum_format_header'] ?? 'X-Enum-Format')
            ->set('type_bridge.includes.json_encoding_options', $includes['json_encoding_options'] ?? JsonResponse::DEFAULT_ENCODING_OPTIONS);
        $container->import(__DIR__ . '/Resources/config/includes.php');
    }
}
