<?php

declare(strict_types=1);

namespace Ssch\TYPO3Rector\Rector\General;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Scalar\String_;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ExtensionArchitecture/DeclarationFile/Index.html
 */
final class ExtEmConfRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var string
     */
    public const TARGET_TYPO3_VERSION_CONSTRAINT = 'target_typo3_version_constraint';

    /**
     * @var string[]
     */
    private const OLD_VALUES_TO_BE_REMOVED = [
        'CGLcompliance_note',
        'uploadfolder',
        'internal',
        'module',
        'CGLcompliance',
        'priority',
        'dependencies',
        'conflicts',
        'loadOrder',
        'createDirs',
        'uploadfolder',
        'shy',
        'modify_tables',
        'lockType',
    ];

    /**
     * @var string[]
     */
    private const PROPERTIES_TO_BOOLEAN = ['clearCacheOnLoad'];

    /**
     * @var string
     */
    private $targetTypo3VersionConstraint = '';

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Assign::class];
    }

    /**
     * @param Assign $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $node->var instanceof ArrayDimFetch) {
            return null;
        }

        if (! $this->isName($node->var->var, 'EM_CONF')) {
            return null;
        }

        if (null === $node->var->dim) {
            return null;
        }

        if (! $this->isName($node->var->dim, '_EXTKEY')) {
            return null;
        }

        if (! $node->expr instanceof Array_) {
            return null;
        }

        if ([] === $node->expr->items || null === $node->expr->items) {
            return null;
        }

        $nodeHasChanged = false;
        foreach ($node->expr->items as $item) {
            /** @var ArrayItem $item */
            if (null === $item->key) {
                continue;
            }

            if ($this->propertyFixString($item)) {
                $item->key = new String_('clearCacheOnLoad');

                $nodeHasChanged = true;
            }

            if ($this->propertyCanBeRemoved($item)) {
                $this->removeNode($item);

                $nodeHasChanged = true;

                continue;
            }

            if ($this->valueResolver->isValues($item->key, self::PROPERTIES_TO_BOOLEAN)) {
                $item->value = $this->nodeFactory->createTrue();

                $nodeHasChanged = true;
            }

            if ('' === $this->targetTypo3VersionConstraint) {
                continue;
            }

            if (! $this->valueResolver->isValue($item->key, 'constraints')) {
                continue;
            }

            if (! $item->value instanceof Array_) {
                continue;
            }

            if (null === $item->value->items) {
                continue;
            }

            foreach ($item->value->items as $constraintItem) {
                /** @var ArrayItem $constraintItem */
                if (null === $constraintItem->key) {
                    continue;
                }

                if (! $this->valueResolver->isValue($constraintItem->key, 'depends')) {
                    continue;
                }

                if (! $constraintItem->value instanceof Array_) {
                    continue;
                }

                if (null === $constraintItem->value->items) {
                    continue;
                }

                foreach ($constraintItem->value->items as $dependsItem) {
                    /** @var ArrayItem $dependsItem */
                    if (null === $dependsItem->key) {
                        continue;
                    }

                    if ($this->valueResolver->isValue($dependsItem->key, 'typo3')) {
                        $dependsItem->value = new String_($this->targetTypo3VersionConstraint);

                        $nodeHasChanged = true;
                    }
                }
            }
        }

        return $nodeHasChanged ? $node : null;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Refactor file ext_emconf.php', [
            new CodeSample(<<<'CODE_SAMPLE'
CODE_SAMPLE
                , <<<'CODE_SAMPLE'
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration): void
    {
        $this->targetTypo3VersionConstraint = $configuration[self::TARGET_TYPO3_VERSION_CONSTRAINT] ? (string) $configuration[self::TARGET_TYPO3_VERSION_CONSTRAINT] : '';
    }

    private function propertyCanBeRemoved(ArrayItem $item): bool
    {
        if (null === $item->key) {
            return false;
        }

        if ($this->valueResolver->isValues($item->key, self::PROPERTIES_TO_BOOLEAN)) {
            return ! (bool) $this->valueResolver->getValue($item->value);
        }

        return $this->valueResolver->isValues($item->key, self::OLD_VALUES_TO_BE_REMOVED);
    }

    private function propertyFixString(ArrayItem $item): bool
    {
        if (null === $item->key) {
            return false;
        }

        return $this->valueResolver->isValue($item->key, 'clearcacheonload');
    }
}
