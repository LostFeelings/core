<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

class NavigationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var NavigationLoader
     */
    private $navigationLoader;

    /**
     * @var string
     */
    private $rootId;

    /**
     * @var string
     */
    private $category1Id;

    /**
     * @var string
     */
    private $category2Id;

    /**
     * @var string
     */
    private $category1_1Id;

    /**
     * @var string
     */
    private $category2_1Id;

    public function setUp(): void
    {
        /* @var EntityRepositoryInterface $repository */
        $this->repository = $this->getContainer()->get('category.repository');

        $this->rootId = Uuid::randomHex();
        $this->category1Id = Uuid::randomHex();
        $this->category2Id = Uuid::randomHex();
        $this->category1_1Id = Uuid::randomHex();
        $this->category2_1Id = Uuid::randomHex();

        $this->navigationLoader = $this->getContainer()->get(NavigationLoader::class);
    }

    public function testTreeBuilderWithSimpleTree(): void
    {
        $loader = new NavigationLoader(
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(EventDispatcher::class)
        );

        $method = ReflectionHelper::getMethod(NavigationLoader::class, 'buildTree');

        /** @var TreeItem[] $treeItems */
        $treeItems = $method->invoke($loader, '1', $this->createSimpleTree());

        static::assertCount(3, $treeItems);
        static::assertCount(2, $treeItems['1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.2']->getChildren());
        static::assertCount(2, $treeItems['1.2']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.1']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.2']->getChildren());
        static::assertCount(0, $treeItems['1.3']->getChildren());
    }

    public function testLoadActiveAndRootCategoryAreSame(): void
    {
        $this->createCategoryTree();

        $tree = $this->navigationLoader->load($this->category1Id, Generator::createSalesChannelContext(), $this->category1Id);
        static::assertInstanceOf(Tree::class, $tree);
    }

    public function testLoadChildOfRootCategory(): void
    {
        $this->createCategoryTree();

        $tree = $this->navigationLoader->load($this->category1_1Id, Generator::createSalesChannelContext(), $this->category1Id);
        static::assertInstanceOf(Tree::class, $tree);
    }

    public function testLoadCategoryNotFound(): void
    {
        static::expectException(CategoryNotFoundException::class);
        $this->navigationLoader->load(Uuid::randomHex(), Generator::createSalesChannelContext(), Uuid::randomHex());
    }

    public function testLoadNotChildOfRootCategoryThrowsException(): void
    {
        $this->createCategoryTree();

        static::expectException(CategoryNotFoundException::class);
        $this->navigationLoader->load($this->category2_1Id, Generator::createSalesChannelContext(), $this->category1Id);
    }

    public function testLoadParentOfRootCategoryThrowsException(): void
    {
        $this->createCategoryTree();

        static::expectException(CategoryNotFoundException::class);
        $this->navigationLoader->load($this->rootId, Generator::createSalesChannelContext(), $this->category1Id);
    }

    public function testLoadDeepNestedTree(): void
    {
        $category1_1_1Id = Uuid::randomHex();
        $category1_1_1_1Id = Uuid::randomHex();

        $this->createCategoryTree();
        $this->repository->upsert([
            [
                'id' => $category1_1_1Id,
                'parentId' => $this->category1_1Id,
                'name' => 'category 1.1.1',
                'children' => [
                    [
                        'id' => $category1_1_1_1Id,
                        'name' => 'category 1.1.1.1',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $tree = $this->navigationLoader->load($category1_1_1_1Id, Generator::createSalesChannelContext(), $this->rootId);

        static::assertNotNull($tree->getChildren($category1_1_1Id));
    }

    private function createSimpleTree(): array
    {
        return [
            new TestTreeAware('1.1', '1'),
            new TestTreeAware('1.1.1', '1.1'),
            new TestTreeAware('1.1.2', '1.1'),
            new TestTreeAware('1.2', '1'),
            new TestTreeAware('1.2.1', '1.2'),
            new TestTreeAware('1.2.1.1', '1.2.1'),
            new TestTreeAware('1.2.2', '1.2'),
            new TestTreeAware('1.2.2.1', '1.2.2'),
            new TestTreeAware('1.3', '1'),
        ];
    }

    private function createCategoryTree(): void
    {
        $this->repository->upsert([
            [
                'id' => $this->rootId,
                'name' => 'root',
                'children' => [
                    [
                        'id' => $this->category1Id,
                        'name' => 'Category 1',
                        'children' => [
                            [
                                'id' => $this->category1_1Id,
                                'name' => 'Category 1.1',
                            ],
                        ],
                    ],
                    [
                        'id' => $this->category2Id,
                        'name' => 'Category 2',
                        'children' => [
                            [
                                'id' => $this->category2_1Id,
                                'name' => 'Category 2.1',
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}

class TestTreeAware extends CategoryEntity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $parentId;

    public function __construct(string $id, string $parentId)
    {
        $this->id = $id;
        $this->parentId = $parentId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getActive(): bool
    {
        return true;
    }

    public function getVisible(): bool
    {
        return true;
    }

    public function getPath(): ?string
    {
        throw new \Exception('Should not be called');
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->getId();
    }
}
