<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoFlowEventAwareExtendsRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $reflection = $node->getClassReflection();

        if (!$reflection->isInterface()) {
            return [];
        }

        if (!$reflection->isSubclassOfClass($this->reflectionProvider->getClass(FlowEventAware::class))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Class %s should not extend FlowEventAware. Flow events should not be derived from each other to make them easier to test',
                $reflection->getName()
            ))
                ->identifier('shopware.flowEventAwareExtend')
                ->build(),
        ];
    }
}
