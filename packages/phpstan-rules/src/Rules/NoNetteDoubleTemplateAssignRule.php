<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\NoNetteDoubleTemplateAssignRule\NoNetteDoubleTemplateAssignRuleTest
 */
final class NoNetteDoubleTemplateAssignRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Avoid double template variable override of "%s"';

    /**
     * @var SimpleNameResolver
     */
    private $simpleNameResolver;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(SimpleNameResolver $simpleNameResolver, NodeFinder $nodeFinder)
    {
        $this->simpleNameResolver = $simpleNameResolver;
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        if (! is_a($classReflection->getName(), 'Nette\Application\UI\Presenter', true)) {
            return [];
        }

        /** @var Assign[] $assigns */
        $assigns = $this->nodeFinder->findInstanceOf($node, Assign::class);

        $duplicatedVariableNames = $this->resolveDuplicatedVaribleNames($assigns);
        if ($duplicatedVariableNames === []) {
            return [];
        }

        $variableNamesString = implode('", ', $duplicatedVariableNames);
        $errorMessage = sprintf(self::ERROR_MESSAGE, $variableNamesString);
        return [$errorMessage];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Presenter;

class SomeClass extends Presenter
{
    public function render()
    {
        $this->template->key = '1';
        $this->template->key = '2';
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Presenter;

class SomeClass extends Presenter
{
    public function render()
    {
        $this->template->key = '2';
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    private function isThisTemplatePropertyFetch(PropertyFetch $propertyFetch): bool
    {
        if (! $propertyFetch instanceof PropertyFetch) {
            return false;
        }

        if (! $propertyFetch->var instanceof PropertyFetch) {
            return false;
        }

        $nestedPropertyFetch = $propertyFetch->var;
        if (! $this->simpleNameResolver->isName($nestedPropertyFetch->var, 'this')) {
            return false;
        }

        return $this->simpleNameResolver->isName($nestedPropertyFetch->name, 'template');
    }

    /**
     * @param Assign[] $assigns
     * @return string[]
     */
    private function resolveDuplicatedVaribleNames(array $assigns): array
    {
        $assignedTemplateVariableNames = $this->resolveUsedTemplateVariableNames($assigns);
        if ($assignedTemplateVariableNames === []) {
            return [];
        }

        $variableNamesToCount = array_count_values($assignedTemplateVariableNames);
        $duplicatedVariableNames = [];
        foreach ($variableNamesToCount as $variableName => $count) {
            if ($count < 2) {
                continue;
            }

            $duplicatedVariableNames[] = $variableName;
        }
        return $duplicatedVariableNames;
    }

    private function resolveUsedTemplateVariableNames(array $assigns): array
    {
        $assignedTemplateVariableNames = [];
        foreach ($assigns as $assign) {
            if (! $assign->var instanceof PropertyFetch) {
                continue;
            }

            if (! $this->isThisTemplatePropertyFetch($assign->var)) {
                continue;
            }

            $templatePropertyFetch = $assign->var;
            $variableName = $this->simpleNameResolver->getName($templatePropertyFetch->name);
            if ($variableName === null) {
                continue;
            }

            $assignedTemplateVariableNames[] = $variableName;
        }
        return $assignedTemplateVariableNames;
    }
}