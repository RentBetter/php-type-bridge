<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use LogicException;
use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Attribute\ValueOfName;
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
use PTGS\TypeBridge\Parser\ValueOfType;
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
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Routing\CompiledRoute;
use Symfony\Component\Routing\Route;
use Throwable;

final class EndpointContractCollector
{
    /** @var array<string, string> requirement regex, as the router holds it => TS type */
    private readonly array $requirementTypes;

    /** A base's file, read once however many queries inherit from it. */
    private readonly ClassFileTypes $files;

    private readonly DomainGuesser $domainGuesser;

    private readonly PhpDocTypeHelper $docHelper;

    private readonly ?AttributeText $toolDescriptions;

    /** What each controller's attributes route it at, read once per class. */
    private readonly DeclaredRoutes $declaredRoutes;

    /** @var array<string, ParsedType|null> each type written, parsed once however many inputs name it */
    private array $parsedTypes = [];

    /** @var array<string, ShapeType|null> by source directory and class: a whole-value field's contract, read once however many forms embed it */
    private array $contracts = [];

    /** @var array<string, bool> by name: whether a project-wide alias admits null, read once however many keys it types */
    private array $configAliasesAdmittingNull = [];

    /** How many aliases and classes one contract reads through, at most. */
    private const int READS = 1000;

    /** How many more the contract being read may read through ({@see self::mayRead()}). */
    private int $readsLeft = self::READS;

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
     * @param string $routingEnv the env the application is deployed in, where no route resolver says:
     *        a tool whose #[Route]s are confined to another is served nowhere it is deployed
     */
    public function __construct(
        ?PhpFileClassLocator $classLocator = null,
        ?DomainGuesser $domainGuesser = null,
        ?PhpDocTypeHelper $docHelper = null,
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
        string $routingEnv = 'prod',
    ) {
        $this->files = ClassFileTypes::sharedBy($files, $docHelper, $domainGuesser, $classLocator);
        $this->domainGuesser = $this->files->domainGuesser;
        $this->docHelper = $this->files->docHelper;
        $this->declaredRoutes = new DeclaredRoutes($routePathResolver->env ?? $routingEnv);
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
                    $served = $this->routePathResolver?->routeFor($className, $method->getName());
                    $route = $served ?? $this->declaredRoute($className, $method->getName());
                    $httpMethod = self::httpMethodOf($route);
                    $contracts[$domain] ??= [];
                    $contracts[$domain][] = new CollectedEndpointContract(
                        name: $endpointName,
                        domain: $domain,
                        controllerClass: $className,
                        methodName: $method->getName(),
                        responses: $responses,
                        request: $this->resolveRequestContract($method, $route, $srcDir, $classFiles),
                        mcp: $this->resolveMcpTool($method, $className, $endpointName, $httpMethod, $route, $served),
                        httpMethod: $httpMethod,
                        httpPath: RoutePathResolver::clientPath($route?->getPath() ?? ''),
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

    private function resolveMcpTool(ReflectionMethod $method, string $className, string $endpointName, string $httpMethod, ?Route $route, ?Route $served): ?CollectedMcpTool
    {
        $attributes = $method->getAttributes(McpTool::class);
        if ([] === $attributes) {
            return null;
        }

        /** @var McpTool $tool */
        $tool = $attributes[0]->newInstance();
        [$path, $unserved] = $this->servedPath($className, $method->getName(), $route, $served);

        return new CollectedMcpTool(
            name: $tool->name ?? $endpointName,
            description: $this->resolveMcpDescription($method, $tool),
            httpMethod: $httpMethod,
            httpPath: $path,
            destructive: $tool->destructive ?? ('GET' !== $httpMethod),
            scopes: $this->resolveMcpScopes($method),
            unserved: $unserved,
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
     * The route a method's class's attributes declare, a class-level #[Route] merged in as the
     * router merges it ({@see DeclaredRoutes}): what a method is described by — its HTTP method, its
     * path and its params — where no routing config serves it. Unlike an MCP tool's, an endpoint the
     * router does not know keeps its declared route rather than failing generation. Null when nothing
     * routes it.
     */
    private function declaredRoute(string $className, string $methodName): ?Route
    {
        $declared = $this->declaredRoutes->routeFor($className, $methodName);
        if (null === $declared && null !== $error = $this->declaredRoutes->loadError($className)) {
            throw new RuntimeException(\sprintf('The #[Route]s of %s cannot be read as Symfony reads them: %s', $className, $error));
        }

        return $declared;
    }

    /**
     * The HTTP method an endpoint is called with: the first its route is for
     * ({@see ControllerAttributeClassLoader::methodsFor()}); GET where it names none.
     */
    private static function httpMethodOf(?Route $route): string
    {
        return (null === $route ? [] : ControllerAttributeClassLoader::methodsFor($route))[0] ?? 'GET';
    }

    /**
     * The path an MCP tool is published with — the one served in the env the application is deployed
     * in — and why there is none. With a route resolver configured it is the router's, and both ways
     * it can fail to supply one are errors rather than fallbacks: a routing file that will not load,
     * or a method the loaded collection does not route — a route confined to another env, or
     * imported in another's section alone, included. Without one it is the route the attributes
     * declare, which must be served in the env too. Either would otherwise publish a path as if it
     * were served, and a tool pointing at a path the application answers 404 to is worse than no
     * tool. The manifest fails on it; the endpoint's TypeScript, described by its route, does not.
     *
     * @return array{0: string, 1: string|null} the path, and why it isn't served
     */
    private function servedPath(string $className, string $methodName, ?Route $route, ?Route $served): array
    {
        $declared = RoutePathResolver::clientPath($route?->getPath() ?? '');
        if (null === $this->routePathResolver) {
            return [$declared, null !== $route && !$this->declaredRoutes->serves($className, $methodName) ? $this->notServed($className, $methodName, 'what its #[Route]s declare') : null];
        }

        if (null !== $error = $this->routePathResolver->loadError()) {
            return [$declared, \sprintf('Cannot resolve served route paths for MCP tools: %s', $error)];
        }

        return null === $served ? [$declared, $this->notServed($className, $methodName, 'the routing configuration')] : [RoutePathResolver::clientPath($served->getPath()), null];
    }

    private function notServed(string $className, string $methodName, string $why): string
    {
        return \sprintf(
            'Endpoint "%s::%s" is exposed as an MCP tool but %s serves no route for it in %s.',
            $className,
            $methodName,
            $why,
            $this->declaredRoutes->env,
        );
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
            ? $this->resolveInputReference(null, $request->path, $srcDir, $classFiles, inQuery: false, nullAsked: false)
            : null;

        // What only a tool reads — a body's null, an embedded form's keys — is read only for one.
        $tool = [] !== $method->getAttributes(McpTool::class);
        $collected = new CollectedEndpointRequest(
            query: null !== $request && null !== $request->query ? $this->resolveFormClass($request->query, $srcDir, $classFiles, inQuery: true, tool: $tool) : null,
            body: null !== $request && null !== $request->body ? $this->resolveFormClass($request->body, $srcDir, $classFiles, inQuery: false, tool: $tool) : null,
            path: $path,
            pathParams: null === $path ? $this->derivePathParamsFromRoute($route) : null,
        );

        return $collected->hasAnyInput() ? $collected : null;
    }

    /**
     * Derives path parameters from the placeholders of the route a method is described by
     * ({@see self::declaredRoute()}): a class-level #[Route]'s, and a routing prefix's, as much as its own.
     * Each segment types as a plain string unless its requirement — the route's, an import's
     * overriding the attribute's — matches a known {@see PathParam} pattern. Null when there is no
     * route or it has no placeholders.
     *
     * @return list<CollectedPathParam>|null
     */
    private function derivePathParamsFromRoute(?Route $route): ?array
    {
        if (null === $route) {
            return null;
        }
        // Named as Symfony's compiler names them: `{!slug}` is `slug` too.
        try {
            $names = array_values(array_filter(self::compiled($route)->getPathVariables(), \is_string(...)));
        } catch (Throwable $exception) {
            throw new RuntimeException(\sprintf('The route %s cannot be compiled as Symfony compiles it: %s', $route->getPath(), $exception->getMessage()), previous: $exception);
        }
        if ([] === $names) {
            return null;
        }

        // Whether the router matches the requirements by character (`u`) or by byte: the option as
        // the route has it — an import's overrides the attribute's; with neither, the router's
        // default, which is not known here.
        $utf8 = $route->hasOption('utf8') ? $route->getOption('utf8') : null;
        $utf8 = \is_bool($utf8) ? $utf8 : null;

        $params = [];
        foreach ($names as $name) {
            $requirement = $route->getRequirement($name);
            $tsType = null !== $requirement ? ($this->requirementTypes[$requirement] ?? 'string') : 'string';
            $params[] = new CollectedPathParam($name, $tsType, $requirement, $utf8, TypeScriptTypes::standsFor($tsType, $this->typeAliases));
        }

        return $params;
    }

    /**
     * The route compiled as the router compiles it. A Unicode requirement needs `utf8`, which a route
     * may leave to the router's default — FrameworkBundle's is on — not known here: one that needs it
     * is compiled with it, which names the same path variables.
     */
    private static function compiled(Route $route): CompiledRoute
    {
        try {
            return $route->compile();
        } catch (LogicException $exception) {
            if ($route->hasOption('utf8')) {
                throw $exception;
            }

            return (clone $route)->setOption('utf8', true)->compile();
        }
    }

    /**
     * @param array<string, string> $classFiles
     * @param class-string $formClass
     * @param bool $tool whether the endpoint is an MCP tool: only its schema turns on whether a body's
     *        key takes null, or on what an embedded form's data class declares
     */
    private function resolveFormClass(string $formClass, string $srcDir, array $classFiles, bool $inQuery, bool $tool): CollectedInputReference
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
            fields: $tool ? $this->withDeclaredContracts($resolvedForm['fields'], $srcDir, $classFiles, $inQuery) : $resolvedForm['fields'],
            inQuery: $inQuery,
            // A query's key that can't be left out turns its Endpoint's `query` required, tool or not.
            nullAsked: $inQuery || $tool,
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
    private function withDeclaredContracts(array $fields, string $srcDir, array $classFiles, bool $inQuery): array
    {
        return array_map(
            fn (CollectedFormField $field): CollectedFormField => $field->withDeclared(
                children: $this->withDeclaredContracts($field->children, $srcDir, $classFiles, $inQuery),
                entryChildren: $this->withDeclaredContracts($field->entryChildren, $srcDir, $classFiles, $inQuery),
                contract: $field->compound && [] === $field->children && null !== $field->dataClass
                    ? $this->contractOf($field->dataClass, $srcDir, $classFiles, $inQuery)
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
    private function contractOf(string $class, string $srcDir, array $classFiles, bool $inQuery): ?ShapeType
    {
        $key = $srcDir . "\0" . $class . "\0" . ($inQuery ? 'query' : 'body');
        if (\array_key_exists($key, $this->contracts)) {
            return $this->contracts[$key];
        }

        return $this->contracts[$key] = $this->readContractOf($class, $srcDir, $classFiles, $inQuery);
    }

    /**
     * @param array<string, string> $classFiles
     */
    private function readContractOf(string $class, string $srcDir, array $classFiles, bool $inQuery): ?ShapeType
    {
        $file = $classFiles[$class] ?? null;
        $self = null === $file || !$this->files->readable($file) ? null : $this->files->definitions($file)['_self'] ?? null;
        if (null === $self) {
            return null;
        }

        [$own, $inherited] = $this->selfContract($self, $class, $file, $srcDir, $classFiles, $inQuery, nullAsked: true);

        return ShapeType::withInherited($own, $inherited);
    }

    /**
     * What a class's `_self` declares: the keys of its own, and for an intersection those it inherits,
     * read where its base is declared ({@see self::inheritedShape()}) — each alias read as it is
     * declared. A `_self` written as another alias, the file's own or one it imports, is that alias's
     * shape, read whole.
     *
     * @param string                $class      the class whose file declares it
     * @param array<string, string> $classFiles
     * @param bool                  $inQuery    whether it is a query's, sent as a query string
     * @param bool                  $nullAsked  whether its keys' null decides anything: not for a path's
     *
     * @return array{0: ShapeType|null, 1: ShapeType|null} its own keys, and those it inherits
     */
    private function selfContract(string $definition, string $class, string $file, string $srcDir, array $classFiles, bool $inQuery, bool $nullAsked): array
    {
        $this->readsLeft = self::READS;
        $self = $this->parseType($definition);
        if ($self instanceof NameRefType && null === $self->class) {
            return [ShapeType::declaredBy($this->readAliases($self, $class, $file, $srcDir, $classFiles, self::reading($file), $inQuery, $nullAsked)), null];
        }

        return [
            $this->withAliasesRead(ShapeType::declaredBy($self), $class, $file, $srcDir, $classFiles, $inQuery, $nullAsked),
            $self instanceof IntersectionType ? $this->inheritedShape($self, $class, $file, $srcDir, $classFiles, $inQuery, $nullAsked) : null,
        ];
    }

    /**
     * @param array<string, string> $classFiles
     * @param class-string|null $formClass
     * @param class-string $ownerClass
     * @param list<\PTGS\TypeBridge\Model\CollectedFormField> $fields
     */
    private function resolveInputReference(?string $formClass, string $ownerClass, string $srcDir, array $classFiles, array $fields = [], bool $inQuery = false, bool $nullAsked = true): CollectedInputReference
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
        [$contract, $inherited] = $this->selfContract($definitions['_self'], $ownerClass, $file, $srcDir, $classFiles, $inQuery, $nullAsked);

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
     * The keys an intersection (`Base & array{…}`) takes from the shape it extends, read as any alias
     * is ({@see self::readAliases()}): where it is declared, through an alias of it, and through that
     * shape's own base in turn. Null when its base can't be read as a shape — one further down that
     * can't be read leaves what is inherited unknown, not the part read.
     *
     * @param string                $class the class whose file the intersection is written in
     * @param array<string, string> $classFiles
     */
    private function inheritedShape(IntersectionType $self, string $class, string $file, string $srcDir, array $classFiles, bool $inQuery, bool $nullAsked): ?ShapeType
    {
        $base = $this->readAliases($self->base, $class, $file, $srcDir, $classFiles, self::reading($file), $inQuery, $nullAsked);

        return $base instanceof ShapeType ? $base : null;
    }

    /**
     * Where an alias a file uses is declared: in the file, or in the class that declares the one it
     * imports — its imports are followed there already ({@see ClassFileTypes::imports()}).
     *
     * @param array<string, string> $classFiles
     *
     * @return array{definition: string, class: string, file: string, name: string}|null
     */
    private function aliasDefinition(string $alias, string $class, string $file, string $srcDir, array $classFiles): ?array
    {
        $definition = $this->files->definitions($file)[$alias] ?? null;
        if (null !== $definition) {
            return ['definition' => $definition, 'class' => $class, 'file' => $file, 'name' => $alias];
        }

        $import = $this->files->imports($class, $file, $srcDir, $classFiles)['imports'][$alias] ?? null;
        $target = null === $import ? null : $classFiles[$import->targetClass] ?? null;
        $definition = null === $target ? null : $this->files->definitions($target)[$import->sourceName()] ?? null;

        return null === $import || null === $target || null === $definition ? null : ['definition' => $definition, 'class' => $import->targetClass, 'file' => $target, 'name' => $import->sourceName()];
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
    private function withAliasesRead(?ShapeType $shape, string $class, string $file, string $srcDir, array $classFiles, bool $inQuery, bool $nullAsked): ?ShapeType
    {
        $read = null === $shape ? null : $this->readAliases($shape, $class, $file, $srcDir, $classFiles, self::reading($file), $inQuery, $nullAsked);

        return $read instanceof ShapeType ? $read : $shape;
    }

    /**
     * Whether a null is asked of a project-wide alias read here ({@see self::configAliasAdmitsNull()})
     * is whether anything turns on it: in a body every key's, which may be sent null to clear it; in a
     * query only a key that can't be left out already — a query string sends a null by leaving the key
     * out, and no item of a list sends one; in a path none, nor of a map's values; and never beside an
     * explicit null (`Alias|null`), which settles it.
     *
     * @param array<string, string> $classFiles
     * @param array<string, true>   $reading the aliases being read, by file and name: one that names
     *        itself, at any remove, stays named there
     * @param bool                  $inQuery whether the type is sent as a query string
     * @param bool                  $nullAsked whether its null decides anything
     */
    private function readAliases(ParsedType $type, string $class, string $file, string $srcDir, array $classFiles, array $reading, bool $inQuery, bool $nullAsked): ParsedType
    {
        $read = fn (ParsedType $part, ?bool $asked = null): ParsedType => $this->readAliases($part, $class, $file, $srcDir, $classFiles, $reading, $inQuery, $asked ?? $nullAsked);

        return match (true) {
            $type instanceof NameRefType => null === $type->class
                ? $this->readAlias($type, $class, $file, $srcDir, $classFiles, $reading, $inQuery, $nullAsked)
                : $this->readClass($type, $type->class, $srcDir, $classFiles, $reading, $inQuery, $nullAsked),
            // An explicit null settles the type's own: what else it admits is not asked of it.
            $type instanceof NullableType => new NullableType($read($type->inner, $type->optional && $nullAsked), $type->optional),
            $type instanceof UnionType => self::union(array_map(static fn (ParsedType $member): ParsedType => $read($member, $nullAsked && !$type->hasNull()), $type->types)),
            $type instanceof ListType => new ListType($read($type->inner, $nullAsked && !$inQuery)),
            // A map's values are never sent null on their own: nothing reads a value's null.
            $type instanceof MapType => new MapType($type->key, $read($type->value, false)),
            $type instanceof ShapeType => new ShapeType(array_map(
                fn (ShapeField $key): ShapeField => new ShapeField($key->name, $read($key->type, $inQuery ? $nullAsked && !$key->mayBeLeftOut(inQuery: true) : $nullAsked), $key->optional),
                $type->fields,
            ), $type->unsealed),
            $type instanceof IntersectionType => self::intersection($type, $read($type->base), $read($type->extra)),
            // The enum the file names, spelt in full, so a reader elsewhere can ask for its values.
            $type instanceof ValueOfType => new ValueOfType($this->docHelper->resolveClassName($type->enumClass, $class, $this->files->classImports($file)) ?? $type->enumClass),
            default => $type,
        };
    }

    /**
     * An alias as it is declared, read in the file that declares it.
     *
     * @param array<string, string> $classFiles
     * @param array<string, true>   $reading
     */
    private function readAlias(NameRefType $alias, string $class, string $file, string $srcDir, array $classFiles, array $reading, bool $inQuery, bool $nullAsked): ParsedType
    {
        $declared = $this->aliasDefinition($alias->name, $class, $file, $srcDir, $classFiles);
        // No alias of the file's: a class it names, as PHP resolves the name there.
        $named = null === $declared && !isset($this->typeAliases[$alias->name]) ? $this->docHelper->resolveClassName($alias->name, $class, $this->files->classImports($file)) : null;
        if (null !== $named) {
            return $this->readClass($alias, $named, $srcDir, $classFiles, $reading, $inQuery, $nullAsked);
        }
        if (null === $declared) {
            // Asked only where it decides something; otherwise read as far as its text says.
            $admitsNull = isset($this->typeAliases[$alias->name])
                && ($nullAsked ? $this->configAliasAdmitsNull($alias->name) : true === TypeScriptTypes::admitsNull($this->typeAliases[$alias->name], $this->typeAliases));

            return $admitsNull ? new NullableType($alias, optional: false) : $alias;
        }

        // Keyed by the name it is declared under, which an import may give another.
        $key = $declared['file'] . "\0" . $declared['name'];
        $parsed = isset($reading[$key]) || !$this->mayRead() ? null : $this->parseType($declared['definition']);
        if (null === $parsed) {
            return $alias;
        }

        // An alias of `?T` is emitted `T`: a key typed with it is no key left out when null.
        $parsed = $parsed instanceof NullableType && $parsed->optional ? $parsed->inner : $parsed;

        return $this->readAliases($parsed, $declared['class'], $declared['file'], $srcDir, $classFiles, [...$reading, $key => true], $inQuery, $nullAsked);
    }

    /**
     * The aliases being read where a class's own `_self` is: the class itself, which a key of it
     * naming it again does not read again.
     *
     * @return array<string, true>
     */
    private static function reading(string $file): array
    {
        return [$file . "\0_self" => true];
    }

    /**
     * Whether one more alias or class may be read through for the contract being read: each is read
     * on every path it is named on, so classes naming one another unroll into every order of them.
     * Past a bound no real contract nears, a name stays a name.
     */
    private function mayRead(): bool
    {
        return --$this->readsLeft >= 0;
    }

    /**
     * A key typed with a class, as the class is sent and its TypeScript names it: an enum, its values
     * — but one that names its value union otherwise (#[ValueOfName]), whose own name is its `_self`;
     * any other class its `_self`, read where it is declared, as an alias is. As written where the
     * class declares none, or names itself at any remove.
     *
     * @param array<string, string> $classFiles
     * @param array<string, true>   $reading
     */
    private function readClass(NameRefType $type, string $class, string $srcDir, array $classFiles, array $reading, bool $inQuery, bool $nullAsked): ParsedType
    {
        if (PhpFileClassLocator::loadsEnum($class) && null === ValueOfName::of($class)) {
            return new ValueOfType($class);
        }

        $file = $classFiles[$class] ?? null;
        if (null === $file) {
            return $type;
        }
        $self = $this->files->emittedDefinitions($class, $file)['_self'] ?? null;
        $key = $file . "\0_self";
        $parsed = null === $self || isset($reading[$key]) || !$this->mayRead() ? null : $this->parseType($self);
        if (null === $parsed) {
            return $type;
        }

        return $this->readAliases($parsed, $class, $file, $srcDir, $classFiles, [...$reading, $key => true], $inQuery, $nullAsked);
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
