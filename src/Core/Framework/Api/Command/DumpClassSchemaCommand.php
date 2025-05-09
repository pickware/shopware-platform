<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Command;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'framework:dump:class:schema',
    description: 'Dumps the schema of the given entity',
)]
#[Package('framework')]
class DumpClassSchemaCommand extends Command
{
    protected string $schemaPath;

    /**
     * @internal
     *
     * @param array{Framework: array{path: string}} $bundles
     */
    public function __construct(array $bundles)
    {
        parent::__construct();
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/SalesChannel/Schema/';
    }

    protected function configure(): void
    {
        $this->addArgument('class', InputArgument::REQUIRED);
        $this->addArgument('name', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var class-string $class */
        $class = $input->getArgument('class');
        $entityClass = $this->getCollectionEntity($class);
        $name = $input->getArgument('name');

        if ($entityClass === null) {
            file_put_contents($this->getFilePath($name), json_encode($this->dumpProperties($class), \JSON_PRETTY_PRINT));
        } else {
            $collection = [
                'type' => 'array',
                'items' => $this->dumpProperties($entityClass),
            ];

            file_put_contents($this->getFilePath($name), json_encode($collection, \JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }

    /**
     * @param class-string $className
     *
     * @return class-string|null
     */
    private function getCollectionEntity(string $className): ?string
    {
        $extends = class_parents($className);
        if ($extends === false) {
            return null;
        }

        if (!isset($extends[Collection::class])) {
            return null;
        }

        if (isset($extends[EntitySearchResult::class])) {
            return null;
        }

        $filePath = (new \ReflectionClass($className))->getFileName();
        if ($filePath === false) {
            return null;
        }

        $stmts = $this->parseFile($filePath);
        if ($stmts === null) {
            return null;
        }

        $findNodes = (new NodeFinder())->findInstanceOf($stmts, ClassMethod::class);

        /** @var ClassMethod $findNode */
        foreach ($findNodes as $findNode) {
            if ((string) $findNode->name === 'getExpectedClass') {
                $nodeStmts = $findNode->stmts;
                if ($nodeStmts === null) {
                    continue;
                }
                /** @var Return_ $returnStatement */
                $returnStatement = $nodeStmts[0];

                /** @var ClassConstFetch $classConst */
                $classConst = $returnStatement->expr;

                /** @phpstan-ignore cast.string (it can be either PhpParser\Node\Expr or PhpParser\Node\Name) */
                return (string) $classConst->class;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Invalid class given %s', $className));
    }

    /**
     * @param array <int, Stmt> $stmts
     *
     * @return array<int, Node>
     */
    private function resolveNames(array $stmts): array
    {
        $nameResolver = new NameResolver();
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nameResolver);

        return $nodeTraverser->traverse($stmts);
    }

    /**
     * @return array <int, Node>|null
     */
    private function parseFile(string $filePath): ?array
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString('7.0'));

        $names = $parser->parse((string) file_get_contents($filePath));

        if ($names === null) {
            return null;
        }

        return $this->resolveNames($names);
    }

    /**
     * @param class-string $entityClass
     *
     * @return array<string, mixed>|null
     */
    private function dumpProperties(string $entityClass, int $deep = 1): ?array
    {
        if ($deep === 3) {
            return null;
        }

        $filePath = (string) (new \ReflectionClass($entityClass))->getFileName();

        $stmts = $this->parseFile($filePath);
        if ($stmts === null) {
            return null;
        }
        $properties = (new NodeFinder())->findInstanceOf($stmts, Property::class);
        $methods = (new NodeFinder())->findInstanceOf($stmts, ClassMethod::class);

        $jsonProperties = [];

        /** @var Property $item */
        foreach ($properties as $item) {
            $name = (string) $item->props[0]->name;

            /** @var ClassMethod $method */
            foreach ($methods as $method) {
                $methodName = (string) $method->name;

                if (!\in_array($methodName, ['get' . ucfirst($name), 'is' . ucfirst($name)], true)) {
                    continue;
                }

                $type = $method->returnType;

                if ($type === null) {
                    continue;
                }

                if ($type instanceof NullableType) {
                    $type = $type->type;
                }

                if (\method_exists($type, 'toString')) {
                    $type = $type->toString();
                }

                if (!\is_string($type)) {
                    // ComplexTypes (e.g. UnionTypes) will be ignored
                    continue;
                }

                if ($type === 'bool') {
                    $type = 'boolean';
                }

                $def = [
                    'type' => $type,
                ];

                if ($type === 'DateTimeInterface') {
                    $def = [
                        'type' => 'string',
                        'format' => 'date-time',
                    ];
                } elseif ($type === 'int') {
                    $def = [
                        'type' => 'integer',
                        'format' => 'int32',
                    ];
                }

                if ($type === 'array') {
                    continue;
                }

                if (class_exists($type)) {
                    $isCollection = $this->getCollectionEntity($type);

                    if ($isCollection) {
                        $inner = $this->dumpProperties($isCollection, $deep + 1);
                        if ($inner === null) {
                            continue;
                        }

                        $def = [
                            'type' => 'array',
                            'items' => $inner,
                        ];
                    } else {
                        $inner = $this->dumpProperties($type, $deep + 1);
                        if ($inner === null) {
                            continue;
                        }

                        $def = $inner;
                    }
                }

                $jsonProperties[$name] = $def;
            }
        }

        return [
            'type' => 'object',
            'properties' => $jsonProperties,
        ];
    }

    private function getFileName(string $className): string
    {
        return str_replace('\\', '_', $className);
    }

    private function getFilePath(string $className): string
    {
        if (!file_exists($this->schemaPath)) {
            if (!mkdir($concurrentDirectory = $this->schemaPath, 0777, true) && !is_dir($concurrentDirectory)) {
                throw ApiException::directoryWasNotCreated($concurrentDirectory);
            }
        }

        return $this->schemaPath . $this->getFileName($className) . '.json';
    }
}
