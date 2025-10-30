<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Support;

use Closure;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Helper responsible for analyzing conditional validation rule closures
 * and extracting normalized rule strings.
 */
class ConditionalRuleAnalyzer
{
    /**
     * Cache of parsed metadata (use statements and namespaces) per file.
     *
     * @var array<string, array{uses: array<string, array<string, string>>, namespaces: array<int, array{start: int, end: ?int, name: ?string}>}>
     */
    private array $fileMetadataCache = [];

    private ?Parser $phpParser = null;

    /**
     * Normalize a conditional rule closure or boolean into a rule string.
     */
    public function normalizeConditionalRule(Closure|bool $condition, string $baseKeyword, string $conditionalKeyword): string
    {
        if (is_bool($condition)) {
            return $condition ? $baseKeyword : '';
        }

        $parameters = $this->extractConditionalParametersFromClosure($condition);

        if ($parameters !== null) {
            $field = $parameters['field'];
            $values = array_map(static function ($value) {
                if (is_string($value)) {
                    return $value;
                }

                if ($value instanceof \BackedEnum) {
                    return $value->value;
                }

                if ($value instanceof \UnitEnum) {
                    return $value->name;
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if (is_numeric($value)) {
                    return (string) $value;
                }

                if (is_object($value) && method_exists($value, '__toString')) {
                    return (string) $value;
                }

                return null;
            }, $parameters['values']);

            $values = array_values(array_filter($values, static fn ($value) => is_string($value) && $value !== ''));

            if (! empty($field) && ! empty($values)) {
                return $conditionalKeyword.':'.$field.','.implode(',', $values);
            }
        }

        try {
            $result = $condition();

            return $result ? $baseKeyword : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Attempt to extract comparison parameters from a conditional closure.
     *
     * @return array{field: string, values: array<int, mixed>}|null
     */
    public function extractConditionalParametersFromClosure(Closure $closure): ?array
    {
        try {
            $reflection = new \ReflectionFunction($closure);
        } catch (\ReflectionException) {
            return null;
        }

        if (! $reflection->isUserDefined()) {
            return null;
        }

        $expression = $this->getClosureExpression($reflection);

        if ($expression === null) {
            return null;
        }

        $file = $reflection->getFileName() ?: '';
        $metadata = $this->getFileMetadata($file);
        $namespace = $this->resolveNamespaceForClosure($reflection, $metadata);
        $scopeClass = $reflection->getClosureScopeClass()?->getName();
        $imports = $this->resolveImportsForNamespace($metadata, $namespace);
        $scopeObject = $reflection->getClosureThis();

        $parser = $this->getPhpParser();

        $parsedExpression = $expression;
        $ast = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $ast = $parser->parse('<?php '.$parsedExpression.';');
                break;
            } catch (Error) {
                $next = $this->trimTrailingDelimiters($parsedExpression);

                if ($next === $parsedExpression) {
                    return null;
                }

                $parsedExpression = $next;
            }
        }

        if ($ast === null) {
            return null;
        }

        if (empty($ast) || ! $ast[0] instanceof Expression) {
            return null;
        }

        $node = $ast[0]->expr;

        return $this->analyzeConditionalExpression($node, $imports, $namespace, $scopeObject, $scopeClass);
    }

    private function getClosureExpression(\ReflectionFunction $reflection): ?string
    {
        $file = $reflection->getFileName();

        if ($file === false) {
            return null;
        }

        $lines = @file($file);

        if ($lines === false) {
            return null;
        }

        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();
        $length = $end - $start + 1;
        $snippet = implode('', array_slice($lines, $start - 1, $length));
        $snippet = trim($snippet);

        $fnPosition = strpos($snippet, 'fn');

        if ($fnPosition !== false) {
            $arrowPos = strpos($snippet, '=>', $fnPosition);
            if ($arrowPos === false) {
                return null;
            }

            $expression = substr($snippet, $arrowPos + 2);

            return $this->cleanClosureExpression($expression);
        }

        if (preg_match('/return\s+(.+?);/s', $snippet, $matches)) {
            return $this->cleanClosureExpression($matches[1]);
        }

        return null;
    }

    private function cleanClosureExpression(string $expression): string
    {
        $expression = trim($expression);
        $expression = preg_replace('/^\s*return\s+/i', '', $expression);
        $expression = trim($expression);

        $expression = rtrim($expression);
        $expression = rtrim($expression, ",;\t\n\r ");

        return trim($expression);
    }

    private function trimTrailingDelimiters(string $expression): string
    {
        $expression = rtrim($expression);

        if ($expression === '') {
            return $expression;
        }

        $lastChar = substr($expression, -1);

        if (in_array($lastChar, [',', ')', ']'], true)) {
            return rtrim(substr($expression, 0, -1));
        }

        return $expression;
    }

    /**
     * @return array{field: string, values: array<int, mixed>}|null
     */
    private function analyzeConditionalExpression(
        Node $node,
        array $imports,
        ?string $namespace,
        ?object $scopeObject,
        ?string $scopeClass
    ): ?array {
        if ($node instanceof ArrowFunction) {
            return $this->analyzeConditionalExpression($node->expr, $imports, $namespace, $scopeObject, $scopeClass);
        }

        if ($node instanceof ClosureExpr) {
            foreach ($node->stmts as $statement) {
                if ($statement instanceof Return_ && $statement->expr !== null) {
                    return $this->analyzeConditionalExpression($statement->expr, $imports, $namespace, $scopeObject, $scopeClass);
                }
            }

            return null;
        }

        if ($node instanceof BinaryOp\Identical || $node instanceof BinaryOp\Equal) {
            $field = $this->extractFieldFromNode($node->left);
            $valueNode = $node->right;

            if ($field === null) {
                $field = $this->extractFieldFromNode($node->right);
                $valueNode = $node->left;
            }

            if ($field === null) {
                return null;
            }

            $value = $this->evaluateNodeValue($valueNode, $imports, $namespace, $scopeObject, $scopeClass);

            if ($value === null) {
                return null;
            }

            return [
                'field' => $field,
                'values' => [$value],
            ];
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $functionName = strtolower($node->name->toString());

            if ($functionName === 'in_array' && count($node->args) >= 2) {
                $field = $this->extractFieldFromNode($node->args[0]->value);
                $valueNode = $node->args[1]->value;

                if ($field === null) {
                    return null;
                }

                $values = $this->evaluateNodeValue($valueNode, $imports, $namespace, $scopeObject, $scopeClass);

                if (! is_array($values) || empty($values)) {
                    return null;
                }

                return [
                    'field' => $field,
                    'values' => array_values(array_filter($values, static fn ($value) => $value !== null)),
                ];
            }
        }

        return null;
    }

    private function extractFieldFromNode(Node $node): ?string
    {
        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $method = $node->name instanceof Identifier ? strtolower($node->name->toString()) : null;

            if (! in_array($method, ['input', 'get', 'query', 'post'], true)) {
                return null;
            }

            if (empty($node->args)) {
                return null;
            }

            $firstArg = $node->args[0]->value;

            if ($firstArg instanceof Scalar\String_) {
                if ($node->var instanceof FuncCall && $node->var->name instanceof Name) {
                    $functionName = strtolower($node->var->name->toString());
                    if ($functionName === 'request') {
                        return $firstArg->value;
                    }
                }

                if ($node->var instanceof Variable && $node->var->name === 'this') {
                    return $firstArg->value;
                }
            }
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $functionName = strtolower($node->name->toString());

            if ($functionName === 'request' && ! empty($node->args)) {
                $firstArg = $node->args[0]->value;

                if ($firstArg instanceof Scalar\String_) {
                    return $firstArg->value;
                }
            }
        }

        if ($node instanceof PropertyFetch) {
            if ($node->var instanceof Variable && $node->var->name === 'this' && $node->name instanceof Identifier) {
                return $node->name->toString();
            }
        }

        return null;
    }

    private function evaluateNodeValue(
        Node $node,
        array $imports,
        ?string $namespace,
        ?object $scopeObject,
        ?string $scopeClass
    ): mixed {
        if ($node instanceof Scalar\String_) {
            return $node->value;
        }

        if ($node instanceof Scalar\LNumber) {
            return $node->value;
        }

        if ($node instanceof Scalar\DNumber) {
            return $node->value;
        }

        if ($node instanceof Scalar\Encapsed) {
            $parts = [];

            foreach ($node->parts as $part) {
                $evaluated = $this->evaluateNodeValue($part, $imports, $namespace, $scopeObject, $scopeClass);

                if ($evaluated === null) {
                    return null;
                }

                $parts[] = $evaluated;
            }

            return implode('', $parts);
        }

        if ($node instanceof ConstFetch) {
            $name = strtolower($node->name->toString());

            return match ($name) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => null,
            };
        }

        if ($node instanceof Array_) {
            $values = [];

            /** @var array<int, ArrayItem|null|mixed> $items */
            $items = $node->items;

            foreach ($items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                $value = $this->evaluateNodeValue($item->value, $imports, $namespace, $scopeObject, $scopeClass);

                $values[] = $value;
            }

            return $values;
        }

        if ($node instanceof ClassConstFetch && $node->name instanceof Identifier) {
            $className = $this->resolveName($node->class, $imports, $namespace, $scopeClass);
            $constName = $node->name->toString();

            if (! class_exists($className) && ! enum_exists($className)) {
                return null;
            }

            try {
                return constant($className.'::'.$constName);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($node instanceof PropertyFetch && $node->name instanceof Identifier) {
            $value = $this->evaluateNodeValue($node->var, $imports, $namespace, $scopeObject, $scopeClass);

            if ($value instanceof \BackedEnum && $node->name->toString() === 'value') {
                return $value->value;
            }

            if (is_object($value) && isset($value->{$node->name->toString()})) {
                return $value->{$node->name->toString()};
            }

            if (is_array($value) && array_key_exists($node->name->toString(), $value)) {
                return $value[$node->name->toString()];
            }
        }

        if ($node instanceof UnaryMinus) {
            $value = $this->evaluateNodeValue($node->expr, $imports, $namespace, $scopeObject, $scopeClass);

            return is_numeric($value) ? -$value : null;
        }

        if ($node instanceof UnaryPlus) {
            return $this->evaluateNodeValue($node->expr, $imports, $namespace, $scopeObject, $scopeClass);
        }

        if ($node instanceof Ternary) {
            $if = $this->evaluateNodeValue($node->if ?? $node->cond, $imports, $namespace, $scopeObject, $scopeClass);
            $else = $this->evaluateNodeValue($node->else, $imports, $namespace, $scopeObject, $scopeClass);

            return $if ?? $else;
        }

        if ($node instanceof BinaryOp\Concat) {
            $left = $this->evaluateNodeValue($node->left, $imports, $namespace, $scopeObject, $scopeClass);
            $right = $this->evaluateNodeValue($node->right, $imports, $namespace, $scopeObject, $scopeClass);

            if ($left === null || $right === null) {
                return null;
            }

            return (string) $left.(string) $right;
        }

        if ($node instanceof BinaryOp\BitwiseOr) {
            $left = $this->evaluateNodeValue($node->left, $imports, $namespace, $scopeObject, $scopeClass);
            $right = $this->evaluateNodeValue($node->right, $imports, $namespace, $scopeObject, $scopeClass);

            if (is_int($left) && is_int($right)) {
                return $left | $right;
            }
        }

        if ($node instanceof BinaryOp\BooleanAnd || $node instanceof BinaryOp\BooleanOr) {
            $left = $this->evaluateNodeValue($node->left, $imports, $namespace, $scopeObject, $scopeClass);
            $right = $this->evaluateNodeValue($node->right, $imports, $namespace, $scopeObject, $scopeClass);

            if (is_bool($left) && is_bool($right)) {
                return $node instanceof BinaryOp\BooleanAnd ? ($left && $right) : ($left || $right);
            }
        }

        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $className = $this->resolveName($node->class, $imports, $namespace, $scopeClass);
            $method = $node->name->toString();

            if (! method_exists($className, $method)) {
                return null;
            }

            $arguments = [];
            foreach ($node->args as $arg) {
                $arguments[] = $this->evaluateNodeValue($arg->value, $imports, $namespace, $scopeObject, $scopeClass);
            }

            try {
                return $className::$method(...$arguments);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($node instanceof ClosureExpr) {
            return null;
        }

        if ($node instanceof ArrowFunction) {
            return null;
        }

        return null;
    }

    private function resolveName(Name|string $name, array $imports, ?string $namespace, ?string $scopeClass = null): string
    {
        if (is_string($name)) {
            return $name;
        }

        if ($name->isFullyQualified()) {
            return $name->toString();
        }

        $first = $name->getFirst();

        $lowerFirst = strtolower($first);

        if (in_array($lowerFirst, ['self', 'static'], true)) {
            return $scopeClass ?? $first;
        }

        if ($lowerFirst === 'parent') {
            if ($scopeClass !== null) {
                $parent = get_parent_class($scopeClass);

                if ($parent !== false) {
                    return $parent;
                }
            }

            return 'parent';
        }

        if (isset($imports[$first])) {
            $remainingParts = array_slice($name->getParts(), 1);
            $remaining = $remainingParts !== [] ? implode('\\', $remainingParts) : '';

            return rtrim($imports[$first].($remaining !== '' ? '\\'.$remaining : ''), '\\');
        }

        $qualified = $name->toString();

        if ($namespace !== null && $namespace !== '') {
            return $namespace.'\\'.$qualified;
        }

        return $qualified;
    }

    private function getFileMetadata(string $file): array
    {
        if ($file === '' || ! file_exists($file)) {
            return [
                'uses' => ['' => []],
                'namespaces' => [],
            ];
        }

        if (isset($this->fileMetadataCache[$file])) {
            return $this->fileMetadataCache[$file];
        }

        $code = file_get_contents($file);

        if ($code === false) {
            return [
                'uses' => ['' => []],
                'namespaces' => [],
            ];
        }

        $parser = $this->getPhpParser();

        try {
            $ast = $parser->parse($code);
        } catch (Error) {
            return [
                'uses' => ['' => []],
                'namespaces' => [],
            ];
        }

        $usesByNamespace = ['' => []];
        $namespaces = [];
        $globalUses = [];

        foreach ($ast ?? [] as $node) {
            if ($node instanceof Namespace_) {
                $namespaceName = $node->name?->toString();
                $key = $namespaceName ?? '';
                $usesByNamespace[$key] = $this->collectUseStatements($node->stmts);

                $namespaces[] = [
                    'start' => $node->getStartLine(),
                    'end' => $node->getEndLine(),
                    'name' => $namespaceName,
                ];
            } elseif ($node instanceof Use_) {
                $globalUses = array_merge($globalUses, $this->collectUsesFromUseNode($node));
            } elseif ($node instanceof GroupUse) {
                $globalUses = array_merge($globalUses, $this->collectUsesFromGroupUseNode($node));
            }
        }

        if (! empty($globalUses)) {
            $usesByNamespace[''] = array_merge($usesByNamespace[''] ?? [], $globalUses);
        }

        return $this->fileMetadataCache[$file] = [
            'uses' => $usesByNamespace,
            'namespaces' => $namespaces,
        ];
    }

    private function collectUseStatements(array $statements): array
    {
        $uses = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Use_) {
                $uses = array_merge($uses, $this->collectUsesFromUseNode($statement));
            } elseif ($statement instanceof GroupUse) {
                $uses = array_merge($uses, $this->collectUsesFromGroupUseNode($statement));
            }
        }

        return $uses;
    }

    private function collectUsesFromUseNode(Use_ $useNode): array
    {
        $uses = [];

        foreach ($useNode->uses as $use) {
            $alias = $use->alias?->toString() ?? $use->name->getLast();
            $uses[$alias] = $use->name->toString();
        }

        return $uses;
    }

    private function collectUsesFromGroupUseNode(GroupUse $groupUse): array
    {
        $uses = [];
        $prefix = $groupUse->prefix->toString();

        foreach ($groupUse->uses as $use) {
            $alias = $use->alias?->toString() ?? $use->name->getLast();
            $uses[$alias] = $prefix.'\\'.$use->name->toString();
        }

        return $uses;
    }

    private function resolveNamespaceForClosure(
        \ReflectionFunction $reflection,
        array $metadata
    ): ?string {
        $line = $reflection->getStartLine();

        foreach ($metadata['namespaces'] as $namespaceInfo) {
            $start = $namespaceInfo['start'];
            $end = $namespaceInfo['end'] ?? PHP_INT_MAX;

            if ($line >= $start && $line <= $end) {
                return $namespaceInfo['name'];
            }
        }

        return null;
    }

    private function resolveImportsForNamespace(array $metadata, ?string $namespace): array
    {
        $key = $namespace ?? '';

        if (isset($metadata['uses'][$key])) {
            return $metadata['uses'][$key];
        }

        return $metadata['uses'][''] ?? [];
    }

    private function getPhpParser(): Parser
    {
        if ($this->phpParser === null) {
            $this->phpParser = (new ParserFactory)->createForNewestSupportedVersion();
        }

        return $this->phpParser;
    }
}
