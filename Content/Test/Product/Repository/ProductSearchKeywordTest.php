<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSearchKeywordTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testAddProductWithSearchKeyword(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10075', $this);

        $id = Uuid::randomHex();

        $this->createProduct($id, ['YTN', 'Search Keyword']);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertContains('YTN', $product->getProductSearchKeywords());
        static::assertContains('Search Keyword', $product->getProductSearchKeywords());
    }

    public function testEditProductWithSearchKeyword(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10075', $this);

        $id = Uuid::randomHex();

        $this->createProduct($id, ['YTN']);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertContains('YTN', $product->getProductSearchKeywords());

        $update = [
            'id' => $id,
            'productSearchKeywords' => ['YTN', 'Search Keyword Update'],
        ];

        $this->repository->update([$update], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertContains('YTN', $product->getProductSearchKeywords());
        static::assertContains('Search Keyword Update', $product->getProductSearchKeywords());
    }

    private function createProduct(string $id, array $searchKeyword): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => '98432def39fc4624b33213a56b8c944d', 'name' => 'test'],
            'tax' => ['id' => '98432def39fc4624b33213a56b8c944d', 'name' => 'test', 'taxRate' => 15],
            'productSearchKeywords' => $searchKeyword,
        ];

        $this->repository->create([$data], $this->context);
    }
}
