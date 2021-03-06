<?php

declare(strict_types=1);

namespace Ssch\TYPO3Rector\Rector\Backend\Utility;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Nop;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @see https://docs.typo3.org/c/typo3/cms-core/master/en-us/Changelog/8.7/Deprecation-80317-DeprecateBackendUtilityGetRecordRaw.html
 */
final class BackendUtilityGetRecordRawRector extends AbstractRector
{
    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isMethodStaticCallOrClassMethodObjectType($node, BackendUtility::class)) {
            return null;
        }

        if (! $this->isName($node->name, 'getRecordRaw')) {
            return null;
        }

        /** @var Arg[] $args */
        $args = $node->args;
        [$firstArgument, $secondArgument, $thirdArgument] = $args;

        $queryBuilderAssignment = $this->createQueryBuilderCall($firstArgument);
        $queryBuilderRemoveRestrictions = $this->createMethodCall(
            $this->createMethodCall(new Variable('queryBuilder'), 'getRestrictions'),
            'removeAll'
        );
        $this->addNodeBeforeNode(new Nop(), $node);
        $this->addNodeBeforeNode($queryBuilderAssignment, $node);
        $this->addNodeBeforeNode($queryBuilderRemoveRestrictions, $node);
        $this->addNodeBeforeNode(new Nop(), $node);

        return $this->fetchQueryBuilderResults($firstArgument, $secondArgument, $thirdArgument);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Migrate the method BackendUtility::editOnClick() to use UriBuilder API', [
            new CodeSample(
                <<<'PHP'
$table = 'fe_users';
$where = 'uid > 5';
$fields = ['uid', 'pid'];
$record = BackendUtility::getRecordRaw($table, $where, $fields);
PHP
                ,
                <<<'PHP'
$table = 'fe_users';
$where = 'uid > 5';
$fields = ['uid', 'pid'];

$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
$queryBuilder->getRestrictions()->removeAll();

$record = $queryBuilder->select(GeneralUtility::trimExplode(',', $fields, true))
    ->from($table)
    ->where(QueryHelper::stripLogicalOperatorPrefix($where))
    ->execute()
    ->fetch();
PHP
            ),
        ]);
    }

    private function createQueryBuilderCall(Arg $firstArgument): Assign
    {
        $queryBuilder = $this->createMethodCall($this->createStaticCall(GeneralUtility::class, 'makeInstance', [
            $this->createClassConstant(ConnectionPool::class, 'class'),
        ]), 'getQueryBuilderForTable', [$this->createArg($firstArgument->value)]);

        return new Assign(new Variable('queryBuilder'), $queryBuilder);
    }

    private function fetchQueryBuilderResults(Arg $table, Arg $where, Arg $fields): MethodCall
    {
        $queryBuilder = new Variable('queryBuilder');

        return $this->createMethodCall(
            $this->createMethodCall(
                $this->createMethodCall(
                    $this->createMethodCall(
                        $this->createMethodCall(
                            $queryBuilder,
                            'select',
                            [
                                $this->createStaticCall(GeneralUtility::class, 'trimExplode', [
                                    new String_(','),
                                    $this->createArg($fields->value),
                                    $this->createTrue(),
                                ]),
                            ]
                        ),
                        'from',
                        [$this->createArg($table->value)]
                    ),
                    'where',
                    [
                        $this->createStaticCall(QueryHelper::class, 'stripLogicalOperatorPrefix', [
                            $this->createArg($where->value),
                        ]),
                    ]
                ),
                'execute'
            ),
            'fetch'
        );
    }
}
