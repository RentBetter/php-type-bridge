<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedEndpointRequest;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedMcpTool;
use PTGS\TypeBridge\Model\CollectedPathParam;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Routing\ControllerAttributeClassLoader;
use PTGS\TypeBridge\Routing\DeclaredRoutes;
use PTGS\TypeBridge\Routing\RequirementType;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use PTGS\TypeBridge\Support\AttributeText;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use PTGS\TypeBridge\Support\TypeScriptTypes;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Throwable;

final class EndpointContractCollector
{
    /** @var array<string, string> requirement regex, as the router holds it => TS type */
    private readonly array $requirementTypes;

    /** A base's file, read once however many queries inherit from it. */
    private readonly ClassFileTypes $files;

    private readonly ?AttributeText $toolDescriptions;

    /** What each controller's attributes route it at, read once per class. */
    private readonly DeclaredRoutes $declaredRoutes;

    /** @var array<string, ParsedType|null> each type written, parsed once however many inputs name it */
    private array $parsedTypes = [];

    /** @var array<string, ShapeType|null> by source directory and class: a whole-value field's contract, read once however many forms embed it */
    private array $contracts = [];

    /** @var array<string, bool> by name: whether a project-wide alias admits null, read once however many keys it types */
    private array $configAliasesAdmittingNull = [];

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
     * @param array<string, string> $typeAliases the project-wide aliases, name => TypeScript type: a key
     *        typed with one that admits null takes null, as one its file declares does
     */
    public function __construct(
        PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        private readonly FormTypeInspector $formTypeInspector = new FormTypeInspector(),
        array $requirementTypes = [],
        private readonly ?string $mcpScopeAttribute = null,
        private readonly ?string $mcpScopeProperty = null,
        private readonly ?string $mcpDescriptionAttribute = null,
        ?string $mcpDescriptionProperty = null,
        private readonly ?RoutePathResolver $routePathResolver = null,
        private readonly PhpDocShapeParser $shapeParser = new PhpDocShapeParser(),
        ?ClassFileTypes $files = null,
        private readonly array $typeAliases = [],
    ) {
        $this->files = $files ?? new ClassFileTypes($docHelper, $domainGuesser, $classLocator);
        $this->declaredRoutes = new DeclaredRoutes();
        // Keyed as the router holds a requirement, anchors off, so a project keying one `^\\d+$` as
        // its attribute writes it finds the `\\d+` the router serves.
        $types = [];
        foreach ([...RequirementType::defaults(), ...$requirementTypes] as $requirement => $type) {
            $types[RoutePathResolver::servedRequirement($requirement) ?? $requirement] = $type;
        }
        $this->requirementTypes = $types;
        $this->toolDescriptions = null === $mcpDescriptionAttribute
            ? null
            : new AttributeText($mcpDescriptionAttribute, $mcpDescriptionProperty, 'mcpDescriptionProperty');
    }

    /**
     * @param array<class-string, CollectedApiResponseClass> $responseIndex
     * @return array<string, list<CollectedEndpointContract>>
     */
    public function collect(string $srcDir, array $responseIndex): array
    {
        $classFiles = $this->files->classesIn($srcDir);
        $contracts = [];
        /** @var array<string, string> derived endpoint name => Class::method that claimed it */
        $seenNames = [];

        foreach ($classFiles as $className => $file) {
            if (!$this->files->loadable($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
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
                    $route = $this->routeOf($className, $method->getName());
                    $httpMethod = self::httpMethodOf($method, $route);
                    $contracts[$domain] ??= [];
                    $contracts[$domain][] = new CollectedEndpointContract(
                        name: $endpointName,
                        domain: $domain,
                        controllerClass: $className,
                        methodName: $method->getName(),
                        responses: $responses,
                        request: $this->resolveRequestContract($method, $route, $srcDir, $classFiles),
                        mcp: $this->resolveMcpTool($method, $className, $endpointName, $httpMethod, $route),
                        httpMethod: $httpMethod,
                        httpPath: $route?->getPath() ?? '',
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

    private function resolveMcpTool(ReflectionMethod $method, string $className, string $endpointName, string $httpMethod, ?Route $route): ?CollectedMcpTool
    {
        $attributes = $method->getAttributes(McpTool::class);
        if ([] === $attributes) {
            return null;
        }

        /** @var McpTool $tool */
        $tool = $attributes[0]->newInstance();

        return new CollectedMcpTool(
            name: $tool->name ?? $endpointName,
            description: $this->resolveMcpDescription($method, $tool),
            httpMethod: $httpMethod,
            httpPath: $this->servedPath($className, $method->getName(), $route),
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
            ?? $this->toolDescriptions?->on($method)
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
     * The route a method is described by — its HTTP method, its path and its params: the one Symfony
     * serves it at when the routing config is given and serves it, its prefixes and an import's
     * overrides included; otherwise the one its class's attributes declare, a class-level #[Route]
     * merged in as the router merges it ({@see DeclaredRoutes}). Unlike an MCP tool's, an endpoint the
     * router does not know keeps its declared route rather than failing generation. Null when nothing
     * routes it.
     */
    private function routeOf(string $className, string $methodName): ?Route
    {
        $served = $this->routePathResolver?->routeFor($className, $methodName);
        if (null !== $served) {
            return $served;
        }

        $declared = $this->declaredRoutes->routeFor($className, $methodName);
        if (null === $declared && null !== $error = $this->declaredRoutes->loadError($className)) {
            throw new RuntimeException(\sprintf('The #[Route]s of %s cannot be read as Symfony reads them: %s', $className, $error));
        }

        return $declared;
    }

    /**
     * The HTTP method an endpoint is called with: of those its route serves, the first its own
     * #[Route] names — Symfony merges a class-level #[Route]'s methods in ahead of the method's, and
     * an import's replace both — else the route's first; GET where it names none.
     */
    private static function httpMethodOf(ReflectionMethod $method, ?Route $route): string
    {
        $served = array_values($route?->getMethods() ?? []);
        $declaredBy = $route?->getOption(ControllerAttributeClassLoader::ATTRIBUTE);
        $attribute = \is_int($declaredBy) ? $method->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF)[$declaredBy] ?? null : null;
        foreach (null === $attribute ? [] : $attribute->newInstance()->methods as $own) {
            if (\in_array(strtoupper($own), $served, true)) {
                return strtoupper($own);
            }
        }

        return $served[0] ?? 'GET';
    }

    /**
     * The path an MCP tool is published with. With a route resolver configured it is the served
     * path, and both ways the router can fail to supply one are errors rather than fallbacks: a
     * routing file that will not load, or a method the loaded collection does not route. Either
     * would otherwise publish the declared path as if it were served, and a tool pointing at a
     * path the application answers 404 to is worse than no tool.
     */
    private function servedPath(string $className, string $methodName, ?Route $route): string
    {
        if (null === $this->routePathResolver) {
            return $route?->getPath() ?? '';
        }

        $served = $this->routePathResolver->pathFor($className, $methodName);
        if (null !== $error = $this->routePathResolver->loadError()) {
            throw new RuntimeException(\sprintf('Cannot resolve served route paths for MCP tools: %s', $error));
        }

        if (null === $served) {
            throw new RuntimeException(\sprintf(
                'Endpoint "%s::%s" is exposed as an MCP tool but the routing configuration serves no route for it.',
                $className,
                $methodName,
            ));
        }

        return $served;
    }

    /**
     * @param array<string, string> $classFiles
     */
    private function resolveRequestContract(ReflectionMethod $method, ?Route $route, string $srcDir, array $classFiles): ?CollectedEndpointRequest
    {
        $attributes = $method->getAttributes(ApiRequest::class);
        /** @var ApiRequest|null $request */
        $request = [] !== $attributes ? $attributes[0]->newInstance() : null;

        // An explicit class on #[ApiRequest(path:)] wins; otherwise the path params are
        // derived from the route's placeholders (each a string unless its requirement
        // refines the type — see PathParam).
        $path = null !== $request && null !== $request->path
            ? $this->resolveInputReference(null, $request->path, $srcDir, $classFiles)
            : null;

        $collected = new CollectedEndpointRequest(
            query: null !== $request && null !== $request->query ? $this->resolveFormClass($request->query, $srcDir, $classFiles) : null,
            body: null !== $request && null !== $request->body ? $this->resolveFormClass($request->body, $srcDir, $classFiles) : null,
            path: $path,
            pathParams: null === $path ? $this->derivePathParamsFromRoute($route) : null,
        );

        return $collected->hasAnyInput() ? $collected : null;
    }

    /**
     * Derives path parameters from the placeholders of the route a method is described by
     * ({@see self::routeOf()}): a class-level #[Route]'s, and a routing prefix's, as much as its own.
     * Each segment types as a plain string unless its requirement — the route's, an import's
     * overriding the attribute's — matches a known {@see PathParam} pattern. Null when there is no
     * route or it has no placeholders.
     *
     * @return list<CollectedPathParam>|null
     */
    private function derivePathParamsFromRoute(?Route $route): ?array
    {
        // Named as Symfony's RouteCompiler reads a placeholder: `{!slug}` is `slug` too.
        if (null === $route || 0 === preg_match_all('/\{!?([\w\x80-\xFF]+)\}/', $route->getPath(), $matches)) {
            return null;
        }

        // Whether the router matches the requirements by character (`u`) or by byte: the option as
        // the route has it — an import's overrides the attribute's; with neither, the router's
        // default, which is not known here.
        $utf8 = $route->hasOption('utf8') ? $route->getOption('utf8') : null;
        $utf8 = \is_bool($utf8) ? $utf8 : null;

        $params = [];
        foreach ($matches[1] as $name) {
            $requirement = $route->getRequirement($name);
            $tsType = null !== $requirement ? ($this->requirementTypes[$requirement] ?? 'string') : 'string';
            $params[] = new CollectedPathParam($name, $tsType, $requirement, $utf8, $this->configAliasStandsFor($tsType));
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
            fields: $this->withDeclaredContracts($resolvedForm['fields'], $srcDir, $classFiles),
        );
    }

    /**
     * The fields, each compound one with no children carrying the `_self` shape its data class
     * declares. Such a field's type reads the submitted value whole (a listener, a transformer),
     * so the built form shows nothing of what it takes and the contract is the only place that
     * says. Every other field is left to its form.
     *
     * @param list<CollectedFormField> $fields
     * @param array<string, string>    $classFiles
     *
     * @return list<CollectedFormField>
     */
    private function withDeclaredContracts(array $fields, string $srcDir, array $classFiles): array
    {
        return array_map(
            fn (CollectedFormField $field): CollectedFormField => $field->withDeclared(
                children: $this->withDeclaredContracts($field->children, $srcDir, $classFiles),
                entryChildren: $this->withDeclaredContracts($field->entryChildren, $srcDir, $classFiles),
                contract: $field->compound && [] === $field->children && null !== $field->dataClass
                    ? $this->contractOf($field->dataClass, $srcDir, $classFiles)
                    : null,
            ),
            $fields,
        );
    }

    /**
     * The keys of the `_self` shape a class in the source tree declares — those it inherits included,
     * and each alias it is written with read as it is declared, as an input's are — or null when it
     * is outside the tree, declares none, or declares one that is not a shape.
     *
     * @param array<string, string> $classFiles
     */
    private function contractOf(string $class, string $srcDir, array $classFiles): ?ShapeType
    {
        $key = $srcDir . "\0" . $class;
        if (\array_key_exists($key, $this->contracts)) {
            return $this->contracts[$key];
        }

        return $this->contracts[$key] = $this->readContractOf($class, $srcDir, $classFiles);
    }

    /**
     * @param array<string, string> $classFiles
     */
    private function readContractOf(string $class, string $srcDir, array $classFiles): ?ShapeType
    {
        $file = $classFiles[$class] ?? null;
        $self = null === $file || !$this->files->readable($file) ? null : $this->files->definitions($file)['_self'] ?? null;
        if (null === $self) {
            return null;
        }

        [$own, $inherited] = $this->selfContract($self, $class, $file, $srcDir, $classFiles);

        return null === $own || null === $inherited ? $own : ShapeType::intersected($inherited, $own);
    }

    /**
     * What a class's `_self` declares: the keys of its own, and for an intersection those it inherits,
     * read where its base is declared ({@see self::inheritedShape()}) — each alias read as it is
     * declared. A `_self` written as another alias, the file's own or one it imports, is that alias's
     * shape, read whole.
     *
     * @param string                $class      the class whose file declares it
     * @param array<string, string> $classFiles
     *
     * @return array{0: ShapeType|null, 1: ShapeType|null} its own keys, and those it inherits
     */
    private function selfContract(string $definition, string $class, string $file, string $srcDir, array $classFiles): array
    {
        $self = $this->parseType($definition);
        if ($self instanceof NameRefType && null === $self->class) {
            return [ShapeType::declaredBy($this->readAliases($self, $class, $file, $srcDir, $classFiles, [])), null];
        }

        return [
            $this->withAliasesRead(ShapeType::declaredBy($self), $class, $file, $srcDir, $classFiles),
            $self instanceof IntersectionType ? $this->inheritedShape($self, $class, $file, $srcDir, $classFiles) : null,
        ];
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

        if (!$this->files->readable($file)) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" could not be read from "%s".',
                $ownerClass,
                $file,
            ));
        }

        $definitions = $this->files->definitions($file);
        if (!isset($definitions['_self'])) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" must declare @phpstan-type _self.',
                $ownerClass,
            ));
        }

        // The keys it inherits are as much its contract as its own: whether a query may be left out,
        // and whether a body key is required or may be cleared, is theirs to say too.
        [$contract, $inherited] = $this->selfContract($definitions['_self'], $ownerClass, $file, $srcDir, $classFiles);

        return new CollectedInputReference(
            formClass: $formClass,
            ownerClass: $ownerClass,
            typeName: $this->docHelper->emittedTypeName('_self', $ownerClass),
            domain: $this->domainGuesser->guess($srcDir, $file),
            fields: $fields,
            contract: $contract,
            inheritedContract: $inherited,
        );
    }

    /**
     * The keys an intersection (`Base & array{…}`) takes from the shape it extends, read where that
     * shape is declared — the same file, or the class it is imported from, following an alias the
     * target only re-imports to where it is declared — and through that shape's own base in turn.
     * Null when its base can't be read as a shape.
     *
     * @param string                $class the class whose file the intersection is written in
     * @param array<string, string> $classFiles
     */
    private function inheritedShape(IntersectionType $self, string $class, string $file, string $srcDir, array $classFiles, int $depth = 0): ?ShapeType
    {
        $base = $depth > 8 ? null : $this->aliasDefinition($self->base->name, $class, $file, $srcDir, $classFiles);
        $parsed = null === $base ? null : $this->parseType($base['definition']);
        $own = null === $base ? null : $this->withAliasesRead(ShapeType::declaredBy($parsed), $base['class'], $base['file'], $srcDir, $classFiles);
        if (null === $base || null === $own) {
            return null;
        }

        $further = $parsed instanceof IntersectionType
            ? $this->inheritedShape($parsed, $base['class'], $base['file'], $srcDir, $classFiles, $depth + 1)
            : null;
        // A base further down that can't be read leaves what is inherited unknown, not the part read.
        if ($parsed instanceof IntersectionType && null === $further) {
            return null;
        }

        return null === $further ? $own : ShapeType::intersected($further, $own);
    }

    /**
     * Where an alias a file uses is declared: in the file, or in the class it is imported from —
     * as that class names it (its `_self`, or a named alias it may itself only import).
     *
     * @param array<string, string> $classFiles
     *
     * @return array{definition: string, class: string, file: string}|null
     */
    private function aliasDefinition(string $alias, string $class, string $file, string $srcDir, array $classFiles, int $depth = 0): ?array
    {
        $definition = $this->files->definitions($file)[$alias] ?? null;
        if (null !== $definition) {
            return ['definition' => $definition, 'class' => $class, 'file' => $file];
        }

        $import = $depth > 8 ? null : $this->files->imports($class, $file, $srcDir, $classFiles)['imports'][$alias] ?? null;
        $target = null === $import ? null : $classFiles[$import->targetClass] ?? null;
        if (null === $import || null === $target || !$this->files->readable($target)) {
            return null;
        }

        return $this->aliasDefinition($import->sourceName(), $import->targetClass, $target, $srcDir, $classFiles, $depth + 1);
    }

    /**
     * The shape as a reader of it alone needs it: each alias it names read as what it is declared —
     * in the file, or where it is imported from, another class's `_self` included — at any depth. So
     * whether a key may be left out or takes null, holds a shape of what keys, a list of what, or which
     * scalar, is said where the shape is read, as the TypeScript says it through the alias. An alias's
     * `?T` is `T`, as its TypeScript is. A project-wide alias, which the config declares in TypeScript,
     * stays named, read as `Alias|null` where that TypeScript admits null; a class's name stays too.
     *
     * @param string                $class the class whose file the shape is written in
     * @param array<string, string> $classFiles
     */
    private function withAliasesRead(?ShapeType $shape, string $class, string $file, string $srcDir, array $classFiles): ?ShapeType
    {
        $read = null === $shape ? null : $this->readAliases($shape, $class, $file, $srcDir, $classFiles, []);

        return $read instanceof ShapeType ? $read : $shape;
    }

    /**
     * @param array<string, string> $classFiles
     * @param array<string, true>   $reading the aliases being read, by file and name: one that names
     *        itself, at any remove, stays named there
     */
    private function readAliases(ParsedType $type, string $class, string $file, string $srcDir, array $classFiles, array $reading): ParsedType
    {
        $read = fn (ParsedType $part): ParsedType => $this->readAliases($part, $class, $file, $srcDir, $classFiles, $reading);

        return match (true) {
            $type instanceof NameRefType => null === $type->class ? $this->readAlias($type, $class, $file, $srcDir, $classFiles, $reading) : $type,
            $type instanceof NullableType => new NullableType($read($type->inner), $type->optional),
            $type instanceof UnionType => self::union(array_map($read, $type->types)),
            $type instanceof ListType => new ListType($read($type->inner)),
            $type instanceof MapType => new MapType($type->key, $read($type->value)),
            $type instanceof ShapeType => new ShapeType(array_map(
                fn (ShapeField $key): ShapeField => new ShapeField($key->name, $read($key->type), $key->optional),
                $type->fields,
            ), $type->unsealed),
            $type instanceof IntersectionType => self::intersection($type, $read($type->base), $read($type->extra)),
            default => $type,
        };
    }

    /**
     * An alias as it is declared, read in the file that declares it.
     *
     * @param array<string, string> $classFiles
     * @param array<string, true>   $reading
     */
    private function readAlias(NameRefType $alias, string $class, string $file, string $srcDir, array $classFiles, array $reading): ParsedType
    {
        $declared = $this->aliasDefinition($alias->name, $class, $file, $srcDir, $classFiles);
        if (null === $declared) {
            return isset($this->typeAliases[$alias->name]) && $this->configAliasAdmitsNull($alias->name)
                ? new NullableType($alias, optional: false)
                : $alias;
        }

        $key = $declared['file'] . "\0" . $alias->name;
        $parsed = isset($reading[$key]) ? null : $this->parseType($declared['definition']);
        if (null === $parsed) {
            return $alias;
        }

        // An alias of `?T` is emitted `T`: a key typed with it is no key left out when null.
        $parsed = $parsed instanceof NullableType && $parsed->optional ? $parsed->inner : $parsed;

        return $this->readAliases($parsed, $declared['class'], $declared['file'], $srcDir, $classFiles, [...$reading, $key => true]);
    }

    /**
     * The TypeScript a project-wide alias stands for, through any alias it names in turn; null where
     * the type is no such alias.
     */
    private function configAliasStandsFor(string $type, int $depth = 0): ?string
    {
        $standsFor = $depth > 8 ? null : $this->typeAliases[trim($type)] ?? null;

        return null === $standsFor ? null : $this->configAliasStandsFor($standsFor, $depth + 1) ?? $standsFor;
    }

    /**
     * Whether a project-wide alias admits null, as its TypeScript says ({@see TypeScriptTypes::admitsNull()}).
     * A key typed with it is one a client may leave out of a query, or clear, where it does, and the
     * Endpoint's AsQuery<T> reads that off the TypeScript as tsc evaluates it: where its text can't
     * say, guessing would have the Endpoint and the tool read the key two ways.
     */
    private function configAliasAdmitsNull(string $name): bool
    {
        $type = $this->typeAliases[$name];

        return $this->configAliasesAdmittingNull[$name] ??= TypeScriptTypes::admitsNull($type, $this->typeAliases) ?? throw new RuntimeException(\sprintf(
            'Whether the project-wide alias "%s" (`%s`) admits null can\'t be read from its TypeScript, and a key of a request is typed with it: whether that key may be left out, or cleared, turns on it. Write the null out — `%2$s | null` where it admits one, `NonNullable<%2$s>` where it doesn\'t.',
            $name,
            $type,
        ));
    }

    /**
     * A union of the members read, a member that is itself a union or admits null spread into it —
     * so a reader of the union sees its null.
     *
     * @param list<ParsedType> $members
     */
    private static function union(array $members): UnionType
    {
        $spread = [];
        foreach ($members as $member) {
            if ($member instanceof UnionType) {
                $spread = [...$spread, ...$member->types];
            } elseif ($member instanceof NullableType && !$member->optional) {
                $spread = [...$spread, $member->inner, new ScalarType('null')];
            } else {
                $spread[] = $member;
            }
        }

        return new UnionType($spread);
    }

    /**
     * An intersection read: the shape its base and its own keys make, where its base reads as one
     * ({@see ShapeType::intersected()}); its own keys read, on the base as written, otherwise.
     */
    private static function intersection(IntersectionType $written, ParsedType $base, ParsedType $extra): ParsedType
    {
        if (!$extra instanceof ShapeType) {
            return $written;
        }

        return $base instanceof ShapeType ? ShapeType::intersected($base, $extra) : new IntersectionType($written->base, $extra);
    }

    private function parseType(string $type): ?ParsedType
    {
        if (\array_key_exists($type, $this->parsedTypes)) {
            return $this->parsedTypes[$type];
        }

        try {
            return $this->parsedTypes[$type] = $this->shapeParser->parse($type);
        } catch (Throwable) {
            return $this->parsedTypes[$type] = null;
        }
    }
}
