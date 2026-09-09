<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedEndpointRequest;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedMcpTool;
use PTGS\TypeBridge\Model\CollectedPathParam;
use PTGS\TypeBridge\Routing\RequirementType;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class EndpointContractCollector
{
    /** @var array<string, string> requirement regex => TS type */
    private readonly array $requirementTypes;

    /**
     * @param array<string, string> $requirementTypes project requirement-regex => TS type,
     *        merged over {@see RequirementType::defaults()}
     * @param string|null $mcpScopeAttribute FQCN of the project's auth-scope attribute; when set,
     *        every #[McpTool] endpoint must carry it (method- or class-level) and its string /
     *        string-backed-enum values become the tool's scopes
     * @param string|null $mcpScopeProperty the one property on that attribute holding the scopes;
     *        without it every public property is read, so an attribute carrying anything else
     *        (a route param => entity class map, say) contributes those values as scopes too
     * @param string|null $mcpDescriptionAttribute FQCN of the project's endpoint-documentation
     *        attribute; when set, an #[McpTool] with no description of its own reads this
     *        attribute's text off the same method before falling back to the docblock summary
     * @param string|null $mcpDescriptionProperty the property on that attribute holding the text
     *        (a string, or a list of strings joined with a space); `description` when not named
     * @param RoutePathResolver|null $routePathResolver resolves the path Symfony actually serves
     *        for an #[McpTool] method. A routing-config `prefix` and any class-level #[Route] are
     *        part of that path, and the method attribute alone cannot see them. Without one a
     *        tool carries the attribute's own path; with one, a tool the router cannot place
     *        fails collection rather than shipping a path the application does not serve
     */
    public function __construct(
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        private readonly FormTypeInspector $formTypeInspector = new FormTypeInspector(),
        array $requirementTypes = [],
        private readonly ?string $mcpScopeAttribute = null,
        private readonly ?string $mcpScopeProperty = null,
        private readonly ?string $mcpDescriptionAttribute = null,
        private readonly ?string $mcpDescriptionProperty = null,
        private readonly ?RoutePathResolver $routePathResolver = null,
    ) {
        $this->requirementTypes = [...RequirementType::defaults(), ...$requirementTypes];
    }

    /**
     * @param array<class-string, CollectedApiResponseClass> $responseIndex
     * @return array<string, list<CollectedEndpointContract>>
     */
    public function collect(string $srcDir, array $responseIndex): array
    {
        $classFiles = $this->classLocator->classesIn($srcDir);
        $contracts = [];
        /** @var array<string, string> derived endpoint name => Class::method that claimed it */
        $seenNames = [];

        foreach ($classFiles as $className => $file) {
            if (!$this->classLocator->isLoadable($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(ApiResponses::class) as $attribute) {
                    /** @var ApiResponses $instance */
                    $instance = $attribute->newInstance();

                    $responses = [];
                    foreach ($instance->responses as $responseClass) {
                        if (!isset($responseIndex[$responseClass])) {
                            throw new RuntimeException(\sprintf(
                                'Endpoint "%s::%s" references unknown response class "%s".',
                                $className,
                                $method->getName(),
                                $responseClass,
                            ));
                        }

                        $responses[] = $responseIndex[$responseClass];
                    }

                    $domain = $this->domainGuesser->guess($srcDir, $file);
                    $endpointName = $this->endpointName($reflection->getShortName(), $method->getName());
                    $claimant = $className . '::' . $method->getName();
                    if (isset($seenNames[$endpointName])) {
                        throw new RuntimeException(\sprintf(
                            'Endpoint name "%s" is derived for both "%s" and "%s". Endpoint names must be'
                            . ' unique — they become TS aliases and MCP tool names. Rename one method'
                            . ' (a named action contributes its name minus any "Action" suffix; an __invoke'
                            . ' controller contributes its class name minus "Controller").',
                            $endpointName,
                            $seenNames[$endpointName],
                            $claimant,
                        ));
                    }
                    $seenNames[$endpointName] = $claimant;
                    $contracts[$domain] ??= [];
                    $contracts[$domain][] = new CollectedEndpointContract(
                        name: $endpointName,
                        domain: $domain,
                        controllerClass: $className,
                        methodName: $method->getName(),
                        responses: $responses,
                        request: $this->resolveRequestContract($method, $srcDir, $classFiles),
                        mcp: $this->resolveMcpTool($method, $endpointName),
                    );
                }
            }
        }

        ksort($contracts);

        return $contracts;
    }

    /**
     * A named action is the endpoint's identity: its name minus any "Action" suffix,
     * PascalCased ("listAccountFeaturesAction" => "ListAccountFeatures"). Grouping
     * actions on one controller therefore never leaks the class name into contract
     * names. An __invoke controller has no method-derived name, so the class base
     * ("ArchiveProjectController" => "ArchiveProject") stands in.
     */
    private function endpointName(string $controllerShortName, string $methodName): string
    {
        if ('__invoke' === $methodName) {
            return preg_replace('/Controller$/', '', $controllerShortName) ?: $controllerShortName;
        }

        $base = str_ends_with($methodName, 'Action') ? substr($methodName, 0, -6) : $methodName;

        return ucfirst('' !== $base ? $base : $methodName);
    }

    private function resolveMcpTool(ReflectionMethod $method, string $endpointName): ?CollectedMcpTool
    {
        $attributes = $method->getAttributes(McpTool::class);
        if ([] === $attributes) {
            return null;
        }

        /** @var McpTool $tool */
        $tool = $attributes[0]->newInstance();
        [$httpMethod, $httpPath] = $this->routeMethodAndPath($method);
        $httpPath = $this->servedPath($method, $httpPath);

        return new CollectedMcpTool(
            name: $tool->name ?? $endpointName,
            description: $this->resolveMcpDescription($method, $tool),
            httpMethod: $httpMethod,
            httpPath: $httpPath,
            destructive: $tool->destructive ?? ('GET' !== $httpMethod),
            scopes: $this->resolveMcpScopes($method),
        );
    }

    /**
     * The tool's LLM-facing description, from the first source with text: the attribute's own
     * `description`, the project's documentation attribute on the same method (when one is
     * configured), then the method's docblock summary. Having none is an error rather than an
     * absent key — a tool the model cannot read is worse than a build break.
     */
    private function resolveMcpDescription(ReflectionMethod $method, McpTool $tool): string
    {
        $description = $this->text($tool->description)
            ?? $this->documentedDescription($method)
            ?? $this->docblockSummary($method);
        if (null !== $description) {
            return $description;
        }

        throw new RuntimeException(\sprintf(
            'Endpoint "%s::%s" is exposed as an MCP tool but has no description: none on #[McpTool], %s, and no docblock summary.',
            $method->getDeclaringClass()->getName(),
            $method->getName(),
            null === $this->mcpDescriptionAttribute
                ? 'no documentation attribute configured (mcpDescriptionAttribute)'
                : \sprintf('no #[%s] on the method', $this->mcpDescriptionAttribute),
        ));
    }

    /**
     * The text of the configured documentation attribute on the method, or null when the method
     * carries none (or it is blank). The property is a string or a list of strings — the shape
     * of property-api's Spec\Api, where a list is the description in paragraphs — joined with a
     * space.
     */
    private function documentedDescription(ReflectionMethod $method): ?string
    {
        if (null === $this->mcpDescriptionAttribute) {
            return null;
        }

        $attributes = $method->getAttributes($this->mcpDescriptionAttribute);
        if ([] === $attributes) {
            return null;
        }

        $reflection = new \ReflectionObject($instance = $attributes[0]->newInstance());
        $propertyName = $this->mcpDescriptionProperty ?? 'description';
        if (!$reflection->hasProperty($propertyName)) {
            throw new RuntimeException(\sprintf(
                'Attribute #[%s] on "%s::%s" has no property "%s" (%s).',
                $this->mcpDescriptionAttribute,
                $method->getDeclaringClass()->getName(),
                $method->getName(),
                $propertyName,
                null === $this->mcpDescriptionProperty
                    ? 'the default; name the right one with the mcpDescriptionProperty config'
                    : 'named by the mcpDescriptionProperty config',
            ));
        }

        $value = $reflection->getProperty($propertyName)->getValue($instance);
        $parts = [];
        foreach (\is_array($value) ? $value : [$value] as $part) {
            if (!\is_string($part)) {
                throw new RuntimeException(\sprintf(
                    'Attribute #[%s] on "%s::%s": property "%s" must hold a string or a list of strings to describe an MCP tool.',
                    $this->mcpDescriptionAttribute,
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $propertyName,
                ));
            }

            if (null !== $part = $this->text($part)) {
                $parts[] = $part;
            }
        }

        return [] === $parts ? null : implode(' ', $parts);
    }

    /**
     * The summary of the method's docblock — the text before the first blank line or tag,
     * joined onto one line — or null when there is no docblock or it opens with a tag.
     */
    private function docblockSummary(ReflectionMethod $method): ?string
    {
        $docComment = $method->getDocComment();
        if (false === $docComment) {
            return null;
        }

        $body = preg_replace('~^/\*\*|\*/$~', '', trim($docComment)) ?? '';
        $summary = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '*'));
            if (str_starts_with($line, '@')) {
                break;
            }

            if ('' === $line) {
                if ([] !== $summary) {
                    break;
                }

                continue;
            }

            $summary[] = $line;
        }

        return [] === $summary ? null : implode(' ', $summary);
    }

    /**
     * Trimmed text, or null for nothing worth keeping — an omitted description and a blank one
     * mean the same thing.
     */
    private function text(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * Reads the tool's required auth scopes from the configured scope attribute (class-level
     * declarations first, then method-level, matching gate semantics where both apply). Scope
     * values are the attribute instance's string / string-backed-enum values — from the one
     * property named by $mcpScopeProperty, or from every public property when none is named.
     * Fails when an #[McpTool] endpoint carries no scope attribute: an ungated generated tool
     * would bypass the project's whitelist-by-default token model.
     *
     * @return list<string>
     */
    private function resolveMcpScopes(ReflectionMethod $method): array
    {
        if (null === $this->mcpScopeAttribute) {
            return [];
        }

        $attributes = [
            ...$method->getDeclaringClass()->getAttributes($this->mcpScopeAttribute),
            ...$method->getAttributes($this->mcpScopeAttribute),
        ];
        if ([] === $attributes) {
            throw new RuntimeException(\sprintf(
                'Endpoint "%s::%s" is exposed as an MCP tool but does not declare #[%s].',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
                $this->mcpScopeAttribute,
            ));
        }

        $scopes = [];
        foreach ($attributes as $attribute) {
            $reflection = new \ReflectionObject($instance = $attribute->newInstance());

            if (null !== $this->mcpScopeProperty) {
                if (!$reflection->hasProperty($this->mcpScopeProperty)) {
                    throw new RuntimeException(\sprintf(
                        'Attribute #[%s] on "%s::%s" has no property "%s" (named by the mcpScopeProperty config).',
                        $this->mcpScopeAttribute,
                        $method->getDeclaringClass()->getName(),
                        $method->getName(),
                        $this->mcpScopeProperty,
                    ));
                }

                $properties = [$reflection->getProperty($this->mcpScopeProperty)];
            } else {
                $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            }

            foreach ($properties as $property) {
                foreach ($this->scopeValues($property->getValue($instance)) as $scope) {
                    if (!\in_array($scope, $scopes, strict: true)) {
                        $scopes[] = $scope;
                    }
                }
            }
        }

        if ([] === $scopes) {
            throw new RuntimeException(\sprintf(
                'Endpoint "%s::%s" declares #[%s] but no scope values could be read from it.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
                $this->mcpScopeAttribute,
            ));
        }

        return $scopes;
    }

    /**
     * @return list<string>
     */
    private function scopeValues(mixed $value): array
    {
        if (\is_string($value)) {
            return [$value];
        }

        if ($value instanceof \BackedEnum && \is_string($value->value)) {
            return [$value->value];
        }

        if (\is_array($value)) {
            $values = [];
            foreach ($value as $entry) {
                $values = [...$values, ...$this->scopeValues($entry)];
            }

            return $values;
        }

        return [];
    }

    /**
     * The path an MCP tool is published with. With a route resolver configured it is the served
     * path, and both ways the router can fail to supply one are errors rather than fallbacks: a
     * routing file that will not load, or a method the loaded collection does not route. Either
     * would otherwise publish the attribute path as if it were served, and a tool pointing at a
     * path the application answers 404 to is worse than no tool.
     */
    private function servedPath(ReflectionMethod $method, string $attributePath): string
    {
        if (null === $this->routePathResolver) {
            return $attributePath;
        }

        $served = $this->routePathResolver->pathFor($method->getDeclaringClass()->getName(), $method->getName());
        if (null !== $error = $this->routePathResolver->loadError()) {
            throw new RuntimeException(\sprintf('Cannot resolve served route paths for MCP tools: %s', $error));
        }

        if (null === $served) {
            throw new RuntimeException(\sprintf(
                'Endpoint "%s::%s" is exposed as an MCP tool but the routing configuration serves no route for it.',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));
        }

        return $served;
    }

    /**
     * @return array{0: string, 1: string} [HTTP method (uppercased, first listed), path template]
     */
    private function routeMethodAndPath(ReflectionMethod $method): array
    {
        $routeAttributes = $method->getAttributes('Symfony\\Component\\Routing\\Attribute\\Route');
        if ([] === $routeAttributes) {
            return ['GET', ''];
        }

        $arguments = $routeAttributes[0]->getArguments();
        $path = $arguments['path'] ?? $arguments[0] ?? '';
        $methods = $arguments['methods'] ?? [];

        $httpMethod = 'GET';
        if (\is_string($methods)) {
            $httpMethod = strtoupper($methods);
        } elseif (\is_array($methods) && \is_string($firstMethod = $methods[0] ?? null)) {
            $httpMethod = strtoupper($firstMethod);
        }

        return [$httpMethod, \is_string($path) ? $path : ''];
    }

    /**
     * @param array<string, string> $classFiles
     */
    private function resolveRequestContract(ReflectionMethod $method, string $srcDir, array $classFiles): ?CollectedEndpointRequest
    {
        $attributes = $method->getAttributes(ApiRequest::class);
        /** @var ApiRequest|null $request */
        $request = [] !== $attributes ? $attributes[0]->newInstance() : null;

        // An explicit class on #[ApiRequest(path:)] wins; otherwise the path params are
        // derived from the #[Route] placeholders (each a string unless its requirement
        // refines the type — see PathParam).
        $path = null !== $request && null !== $request->path
            ? $this->resolveInputReference(null, $request->path, $srcDir, $classFiles)
            : null;

        $collected = new CollectedEndpointRequest(
            query: null !== $request && null !== $request->query ? $this->resolveFormClass($request->query, $srcDir, $classFiles) : null,
            body: null !== $request && null !== $request->body ? $this->resolveFormClass($request->body, $srcDir, $classFiles) : null,
            path: $path,
            pathParams: null === $path ? $this->derivePathParamsFromRoute($method) : null,
        );

        return $collected->hasAnyInput() ? $collected : null;
    }

    /**
     * Derives path parameters from the method's #[Route] placeholders. Each segment types as a
     * plain string unless its route requirement matches a known {@see PathParam} pattern. Returns
     * null when there is no #[Route] or it carries no placeholders.
     *
     * @return list<CollectedPathParam>|null
     */
    private function derivePathParamsFromRoute(ReflectionMethod $method): ?array
    {
        $routeAttributes = $method->getAttributes('Symfony\\Component\\Routing\\Attribute\\Route');
        if ([] === $routeAttributes) {
            return null;
        }

        $arguments = $routeAttributes[0]->getArguments();
        $pathTemplate = $arguments['path'] ?? $arguments[0] ?? null;
        if (!\is_string($pathTemplate) || 0 === preg_match_all('/\{(\w+)\}/', $pathTemplate, $matches)) {
            return null;
        }

        $requirements = $arguments['requirements'] ?? [];
        if (!\is_array($requirements)) {
            $requirements = [];
        }

        $params = [];
        foreach ($matches[1] as $name) {
            $requirement = $requirements[$name] ?? null;
            $requirement = \is_string($requirement) ? $requirement : null;
            $tsType = null !== $requirement ? ($this->requirementTypes[$requirement] ?? 'string') : 'string';
            $params[] = new CollectedPathParam($name, $tsType, $requirement);
        }

        return $params;
    }

    /**
     * @param array<string, string> $classFiles
     * @param class-string $formClass
     */
    private function resolveFormClass(string $formClass, string $srcDir, array $classFiles): CollectedInputReference
    {
        if (!isset($classFiles[$formClass])) {
            throw new RuntimeException(\sprintf(
                'Endpoint form class "%s" was not found in "%s".',
                $formClass,
                $srcDir,
            ));
        }

        $resolvedForm = $this->formTypeInspector->inspect($formClass);
        if (null === $resolvedForm['dataClass']) {
            throw new RuntimeException(\sprintf(
                'Form "%s" must configure a non-null data_class for TypeBridge request contracts.',
                $formClass,
            ));
        }

        return $this->resolveInputReference(
            formClass: $formClass,
            ownerClass: $resolvedForm['dataClass'],
            srcDir: $srcDir,
            classFiles: $classFiles,
            fields: $resolvedForm['fields'],
        );
    }

    /**
     * @param array<string, string> $classFiles
     * @param class-string|null $formClass
     * @param class-string $ownerClass
     * @param list<\PTGS\TypeBridge\Model\CollectedFormField> $fields
     */
    private function resolveInputReference(?string $formClass, string $ownerClass, string $srcDir, array $classFiles, array $fields = []): CollectedInputReference
    {
        $file = $classFiles[$ownerClass] ?? null;
        if (null === $file) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" was not found in "%s".',
                $ownerClass,
                $srcDir,
            ));
        }

        $content = file_get_contents($file);
        if (false === $content) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" could not be read from "%s".',
                $ownerClass,
                $file,
            ));
        }

        $definitions = $this->docHelper->extractPhpStanTypes($content);
        if (!isset($definitions['_self'])) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" must declare @phpstan-type _self.',
                $ownerClass,
            ));
        }

        return new CollectedInputReference(
            formClass: $formClass,
            ownerClass: $ownerClass,
            typeName: $this->emittedTypeName($ownerClass),
            domain: $this->domainGuesser->guess($srcDir, $file),
            fields: $fields,
        );
    }

    /**
     * Mirrors PhpDocTypeCollector::_self name emission for endpoint input references.
     *
     * @param class-string $ownerClass
     */
    private function emittedTypeName(string $ownerClass): string
    {
        $shortName = $this->shortName($ownerClass);
        if (enum_exists($ownerClass)) {
            return $shortName . 'Data';
        }

        return $shortName;
    }

    /**
     * @param class-string $className
     */
    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return $className;
        }

        return substr($className, $position + 1);
    }
}
